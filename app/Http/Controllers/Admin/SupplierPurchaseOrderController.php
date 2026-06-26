<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Brand;
use App\Models\Company;
use App\Models\Currency;
use App\Models\CustomerPurchaseOrder;
use App\Models\CustomerPurchaseOrderItem;
use App\Models\Document;
use App\Models\Presentation;
use App\Models\Supplier;
use App\Models\SupplierAccount;
use App\Models\SupplierPurchaseOrder;
use App\Models\SupplierPurchaseOrderItem;
use App\Models\Ubigeo;
use App\Models\Unit;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class SupplierPurchaseOrderController extends Controller
{
    private const STATUS_DRAFT = 'draft';
    private const STATUS_SENT = 'sent';
    private const STATUS_APPROVED = 'approved';
    private const STATUS_RECEIVED = 'received';
    private const STATUS_CANCELLED = 'cancelled';
    private const STATUS_INVOICED = 'invoiced';

    public function index()
    {
        $companies = Company::query()
            ->where('status', true)
            ->orderBy('business_name')
            ->get();

        $suppliers = Supplier::query()
            ->where('status', 'ACTIVE')
            ->orderBy('business_name')
            ->get(['id', 'business_name', 'short_name', 'ruc', 'payment_condition']);

        $supplierAccounts = SupplierAccount::query()
            ->with('bank:id,description', 'currency:id,code')
            ->where('status', 'ACTIVE')
            ->orderBy('account_number')
            ->get();

        $currencies = Currency::query()
            ->where('status', 'ACTIVE')
            ->orderBy('description')
            ->get();

        $customerPurchaseOrders = CustomerPurchaseOrder::query()
            ->with(
                'customer:id,business_name,full_name,first_name,last_name',
                'currency:id,code,symbol'
            )
            ->orderByDesc('id')
            ->get(['id', 'code', 'customer_id', 'currency_id', 'grand_total', 'status']);

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

        $ubigeos = Ubigeo::query()
            ->orderBy('department')
            ->orderBy('province')
            ->orderBy('district')
            ->limit(3000)
            ->get();

        return view('admin.supplier-purchase-orders.index', compact(
            'companies',
            'suppliers',
            'supplierAccounts',
            'currencies',
            'customerPurchaseOrders',
            'articles',
            'units',
            'presentations',
            'brands',
            'ubigeos'
        ));
    }

    public function list()
    {
        $orders = SupplierPurchaseOrder::query()
            ->with([
                'supplier:id,business_name,short_name,ruc',
                'company:id,business_name,trade_name',
                'currency:id,code,symbol,description',
                'documents' => function ($query) {
                    $query->where('observation', 'PDF_GENERATED_SUPPLIER_PURCHASE_ORDER')
                        ->where('status', 'ACTIVE')
                        ->where('mime_type', 'application/pdf');
                },
            ])
            ->orderByDesc('id');

        return DataTables::of($orders)
            ->addIndexColumn()
            ->addColumn('supplier', function (SupplierPurchaseOrder $order) {
                return $order->supplier?->short_name
                    ?? $order->supplier?->business_name
                    ?? '-';
            })
            ->addColumn('company', function (SupplierPurchaseOrder $order) {
                return $order->company?->trade_name
                    ?? $order->company?->business_name
                    ?? '-';
            })
            ->addColumn('currency', function (SupplierPurchaseOrder $order) {
                return $order->currency?->code
                    ?? $order->currency?->description
                    ?? '-';
            })
            ->editColumn('grand_total', function (SupplierPurchaseOrder $order) {
                $symbol = $order->currency?->symbol ?? '';

                return trim($symbol . ' ' . number_format((float) $order->grand_total, 2));
            })
            ->editColumn('status', function (SupplierPurchaseOrder $order) {
                $statuses = $this->statusPresentation();
                $status = $statuses[$order->status] ?? [
                    'label' => ucfirst((string) $order->status),
                    'class' => 'badge-light text-dark border',
                    'icon' => 'fas fa-info-circle',
                ];

                return sprintf(
                    '<div class="d-flex justify-content-center">
                        <span class="badge %s rounded-pill px-3 py-2 shadow-sm font-weight-bold"
                            style="min-width:140px;font-size:11px;letter-spacing:.2px;">
                            <i class="%s mr-1" aria-hidden="true"></i>
                            %s
                        </span>
                    </div>',
                    $status['class'],
                    $status['icon'],
                    e($status['label'])
                );
            })
            ->editColumn('created_at', function (SupplierPurchaseOrder $order) {
                return $order->created_at?->format('d/m/Y H:i') ?? '-';
            })
            ->addColumn('acciones', function (SupplierPurchaseOrder $order) {
                $pdfDocument = $order->documents
                    ->first(fn (Document $document) => $document->file_path
                        && Storage::disk('public')->exists($document->file_path));
                $pdfUrl = $pdfDocument
                    ? Storage::disk('public')->url($pdfDocument->file_path)
                    : null;

                return view(
                    'admin.supplier-purchase-orders.partials.acciones',
                    compact('order', 'pdfUrl')
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

    public function supplierAccounts(Supplier $supplier)
    {
        $accounts = $supplier->accounts()
            ->with('bank:id,description', 'currency:id,code')
            ->where('status', 'ACTIVE')
            ->orderBy('account_number')
            ->get();

        return response()->json(['accounts' => $accounts]);
    }

    public function customerPurchaseOrderItems(CustomerPurchaseOrder $customerPurchaseOrder)
    {
        return $this->customerPurchaseOrderItemsResponse(collect([$customerPurchaseOrder]));
    }

    public function loadCustomerOrderItems(Request $request)
    {
        $validated = $request->validate([
            'customer_purchase_order_ids' => ['required', 'array', 'min:1'],
            'customer_purchase_order_ids.*' => [
                'distinct',
                Rule::exists('customer_purchase_orders', 'id')
                    ->whereNull('deleted_at'),
            ],
        ]);

        $ids = collect($validated['customer_purchase_order_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $orders = CustomerPurchaseOrder::query()
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn (CustomerPurchaseOrder $order) => $ids->search($order->id))
            ->values();

        return $this->customerPurchaseOrderItemsResponse($orders);
    }

    public function store(Request $request)
    {
        return $this->saveOrder($request);
    }

    public function show(SupplierPurchaseOrder $supplierPurchaseOrder)
    {
        $supplierPurchaseOrder->load([
            'company',
            'supplier',
            'supplierAccount.bank',
            'supplierAccount.currency',
            'currency',
            'customerPurchaseOrder',
            'customerPurchaseOrders.customer',
            'quote',
            'marketStudy',
            'destinationUbigeo',
            'creator',
            'updater',
            'documents',
            'items.article',
            'items.marketStudyItem',
            'items.quoteItem',
            'items.customerPurchaseOrderItem',
            'items.unit',
            'items.presentation',
            'items.brand',
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $supplierPurchaseOrder,
        ]);
    }

    public function edit(SupplierPurchaseOrder $supplierPurchaseOrder)
    {
        return $this->show($supplierPurchaseOrder);
    }

    public function update(
        Request $request,
        SupplierPurchaseOrder $supplierPurchaseOrder
    ) {
        return $this->saveOrder($request, $supplierPurchaseOrder);
    }

    public function destroy(SupplierPurchaseOrder $supplierPurchaseOrder)
    {
        try {
            $supplierPurchaseOrder->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Orden de compra a proveedor eliminada correctamente.',
            ]);
        } catch (\Throwable $e) {
            Log::error('Error deleting supplier purchase order: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo eliminar la orden de compra a proveedor.',
            ], 500);
        }
    }

    private function saveOrder(
        Request $request,
        ?SupplierPurchaseOrder $order = null
    ) {
        $validated = $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'supplier_account_id' => [
                'nullable',
                Rule::exists('supplier_accounts', 'id')
                    ->where('supplier_id', $request->input('supplier_id')),
            ],
            'currency_id' => ['required', 'exists:currencies,id'],
            'customer_purchase_order_ids' => ['required', 'array', 'min:1'],
            'customer_purchase_order_ids.*' => [
                'distinct',
                Rule::exists('customer_purchase_orders', 'id')
                    ->whereNull('deleted_at'),
            ],
            'customer_purchase_order_id' => ['nullable', 'exists:customer_purchase_orders,id'],
            'quote_id' => ['nullable', 'exists:quotes,id'],
            'market_study_id' => ['nullable', 'exists:market_studies,id'],
            'order_type' => ['nullable', Rule::in(['articles', 'services'])],
            'payment_condition' => ['nullable', Rule::in($this->paymentConditionOptions())],
            'delivery_type' => ['nullable', Rule::in($this->deliveryTypeOptions())],
            'transport_type' => ['nullable', Rule::in($this->transportTypeOptions())],
            'shipping_address' => ['nullable', 'string'],
            'destination_ubigeo_id' => ['nullable', 'exists:ubigeos,id'],
            'destination_text' => ['nullable', 'string', 'max:255'],
            'payment_method' => ['nullable', Rule::in($this->paymentMethodOptions())],
            'document_type' => ['nullable', Rule::in($this->documentTypeOptions())],
            'affect_igv' => ['nullable', 'boolean'],
            'observations' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in(array_keys($this->statusPresentation()))],
            'items' => ['required', 'array', 'min:1'],
            'items.*.article_id' => ['required', 'exists:articles,id'],
            'items.*.market_study_item_id' => ['nullable', 'exists:market_study_items,id'],
            'items.*.quote_item_id' => ['nullable', 'exists:quote_items,id'],
            'items.*.customer_purchase_order_item_id' => [
                'nullable',
                'exists:customer_purchase_order_items,id',
            ],
            'items.*.article_code' => ['nullable', 'string', 'max:255'],
            'items.*.billing_name_snapshot' => ['required', 'string', 'max:255'],
            'items.*.note' => ['nullable', 'string'],
            'items.*.unit_id' => ['nullable', 'exists:units,id'],
            'items.*.presentation_id' => ['nullable', 'exists:presentations,id'],
            'items.*.brand_id' => ['nullable', 'exists:brands,id'],
            'items.*.origin' => ['nullable', 'string', 'max:100'],
            'items.*.expiration_date' => ['nullable', 'date'],
            'items.*.cost_type' => ['nullable', 'string', 'max:100'],
            'items.*.reference_purchase_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.status' => ['nullable', 'string', 'max:30'],
        ]);

        $generatedPdfPath = null;
        $generatedPdfUrl = null;
        $generatedDocumentId = null;
        $pdfError = null;

        try {
            return DB::transaction(function () use (
                $validated,
                $order,
                &$generatedPdfPath,
                &$generatedPdfUrl,
                &$generatedDocumentId,
                &$pdfError
            ) {
                $customerOrderIds = collect($validated['customer_purchase_order_ids'])
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values()
                    ->all();
                $affectIgv = (bool) ($validated['affect_igv'] ?? false);
                $preparedItems = $this->prepareItems($validated['items'], $affectIgv);
                $totals = $this->calculateTotals($preparedItems);

                $orderData = [
                    'company_id' => $validated['company_id'],
                    'supplier_id' => $validated['supplier_id'],
                    'supplier_account_id' => $validated['supplier_account_id'] ?? null,
                    'currency_id' => $validated['currency_id'],
                    'customer_purchase_order_id' => $customerOrderIds[0] ?? null,
                    'quote_id' => $validated['quote_id'] ?? null,
                    'market_study_id' => $validated['market_study_id'] ?? null,
                    'order_type' => $validated['order_type'] ?? 'articles',
                    'payment_condition' => $validated['payment_condition'] ?? null,
                    'delivery_type' => $validated['delivery_type'] ?? null,
                    'transport_type' => $validated['transport_type'] ?? null,
                    'shipping_address' => $this->upperOrNull(
                        $validated['shipping_address'] ?? null
                    ),
                    'destination_ubigeo_id' => $validated['destination_ubigeo_id'] ?? null,
                    'destination_text' => $this->upperOrNull(
                        $validated['destination_text'] ?? null
                    ),
                    'payment_method' => $validated['payment_method'] ?? null,
                    'document_type' => $validated['document_type'] ?? null,
                    'affect_igv' => $affectIgv,
                    'observations' => $this->upperOrNull($validated['observations'] ?? null),
                    'subtotal' => $totals['subtotal'],
                    'igv' => $totals['igv'],
                    'grand_total' => $totals['grand_total'],
                    'status' => $validated['status'] ?? $order?->status ?? self::STATUS_DRAFT,
                    'updated_by' => Auth::id(),
                ];

                if ($order) {
                    $order->update($orderData);
                    $order->items()->delete();
                } else {
                    $orderData['code'] = $this->nextCode();
                    $orderData['created_by'] = Auth::id();
                    $order = SupplierPurchaseOrder::create($orderData);
                }

                $wasRecentlyCreated = $order->wasRecentlyCreated;

                foreach ($preparedItems as $item) {
                    $order->items()->create($item);
                }

                $order->customerPurchaseOrders()->sync($customerOrderIds);

                try {
                    $pdfData = $this->generateSupplierPurchaseOrderPdf($order->fresh([
                        'company',
                        'supplier',
                        'supplierAccount.bank',
                        'supplierAccount.currency',
                        'currency',
                        'destinationUbigeo',
                        'customerPurchaseOrders.customer',
                        'items.unit',
                        'items.presentation',
                        'items.brand',
                    ]));

                    $generatedPdfPath = $pdfData['path'];
                    $generatedPdfUrl = $pdfData['url'];
                    $generatedDocumentId = $pdfData['document']->id;
                } catch (\Throwable $pdfException) {
                    $pdfError = 'La orden se guardo, pero no se pudo generar el PDF.';

                    Log::error('Error generating supplier purchase order PDF: ' . $pdfException->getMessage());
                }

                return response()->json([
                    'status' => 'success',
                    'message' => $wasRecentlyCreated
                        ? 'Orden de compra a proveedor registrada correctamente.'
                        : 'Orden de compra a proveedor actualizada correctamente.',
                    'data' => $order->fresh(['items', 'customerPurchaseOrders']),
                    'pdf_path' => $generatedPdfPath,
                    'pdf_url' => $generatedPdfUrl,
                    'document_id' => $generatedDocumentId,
                    'pdf_error' => $pdfError,
                ], $wasRecentlyCreated ? 201 : 200);
            });
        } catch (\Throwable $e) {
            if ($generatedPdfPath && Storage::disk('public')->exists($generatedPdfPath)) {
                Storage::disk('public')->delete($generatedPdfPath);
            }

            Log::error('Error saving supplier purchase order: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo guardar la orden de compra a proveedor.',
            ], 500);
        }
    }

    private function prepareItems(array $items, bool $affectIgv): array
    {
        return collect($items)
            ->map(function (array $item) use ($affectIgv) {
                $quantity = round((float) $item['quantity'], 2);
                $unitPrice = round((float) $item['unit_price'], 2);
                $subtotal = round($quantity * $unitPrice, 2);
                $taxAmount = $affectIgv ? round($subtotal * 0.18, 2) : 0;

                return [
                    'article_id' => $item['article_id'],
                    'market_study_item_id' => $item['market_study_item_id'] ?? null,
                    'quote_item_id' => $item['quote_item_id'] ?? null,
                    'customer_purchase_order_item_id' => $item['customer_purchase_order_item_id'] ?? null,
                    'article_code' => $this->upperOrNull($item['article_code'] ?? null),
                    'billing_name_snapshot' => $this->upperOrNull(
                        $item['billing_name_snapshot'] ?? 'ARTICULO'
                    ),
                    'note' => $this->upperOrNull($item['note'] ?? null),
                    'unit_id' => $item['unit_id'] ?? null,
                    'presentation_id' => $item['presentation_id'] ?? null,
                    'brand_id' => $item['brand_id'] ?? null,
                    'origin' => $this->upperOrNull($item['origin'] ?? null),
                    'expiration_date' => $item['expiration_date'] ?? null,
                    'cost_type' => $this->upperOrNull($item['cost_type'] ?? null),
                    'reference_purchase_price' => round(
                        (float) ($item['reference_purchase_price'] ?? 0),
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

    private function customerPurchaseOrderItemsResponse($orders)
    {
        $orders->each(function (CustomerPurchaseOrder $order) {
            $order->load([
                'customer:id,business_name,full_name,first_name,last_name',
                'items.article',
                'items.unit',
                'items.presentation',
                'items.brand',
            ]);
        });

        $firstOrder = $orders->first();
        $items = $orders->flatMap(function (CustomerPurchaseOrder $order) {
            $customerName = $order->customer?->business_name
                ?? $order->customer?->full_name
                ?? trim(
                    ($order->customer?->first_name ?? '') . ' ' .
                    ($order->customer?->last_name ?? '')
                )
                ?: null;

            return $order->items
                ->reject(fn (CustomerPurchaseOrderItem $item) => strtolower((string) $item->status) === 'deleted')
                ->map(function (CustomerPurchaseOrderItem $item) use ($order, $customerName) {
                return array_merge(
                    $this->sourceItemPayload($item, 'customer_purchase_order_item_id'),
                    [
                        'customer_purchase_order_id' => $order->id,
                        'customer_purchase_order_code' => $order->code,
                        'customer_name' => $customerName,
                    ]
                );
            });
        })->values();

        return response()->json([
            'customer_purchase_order_ids' => $orders->pluck('id')->values(),
            'company_id' => $firstOrder?->company_id,
            'currency_id' => $firstOrder?->currency_id,
            'quote_id' => $firstOrder?->quote_id,
            'items' => $items,
        ]);
    }

    private function sourceItemPayload(Model $item, string $sourceKey): array
    {
        $article = $item->article;
        $quantity = round((float) ($item->quantity ?? 1), 2);
        $unitPrice = round((float) ($item->unit_price ?? $item->cost_price ?? 0), 2);

        return [
            $sourceKey => $item->id,
            'article_id' => $item->article_id,
            'market_study_item_id' => $item->market_study_item_id ?? null,
            'quote_item_id' => $sourceKey === 'quote_item_id' ? $item->id : ($item->quote_item_id ?? null),
            'customer_purchase_order_item_id' => $sourceKey === 'customer_purchase_order_item_id'
                ? $item->id
                : null,
            'article_code' => $item->article_code
                ?? $item->article_code_snapshot
                ?? $article?->code,
            'billing_name_snapshot' => $item->billing_name_snapshot
                ?? $article?->billing_name
                ?? 'ARTICULO',
            'note' => $item->note ?? null,
            'unit_id' => $item->unit_id ?? $article?->unit_id,
            'presentation_id' => $item->presentation_id ?? $article?->presentation_id,
            'brand_id' => $item->brand_id ?? $article?->brand_id,
            'origin' => $item->origin ?? null,
            'expiration_date' => $item->expiration_date ? (string) $item->expiration_date : null,
            'cost_type' => $item->cost_type ?? $item->cost_condition_snapshot ?? 'PESO',
            'reference_purchase_price' => $unitPrice,
            'quantity' => $quantity > 0 ? $quantity : 1,
            'unit_price' => $unitPrice,
        ];
    }

    private function nextCode(): string
    {
        $lastNumber = SupplierPurchaseOrder::withTrashed()
            ->where('code', 'like', 'OC%')
            ->pluck('code')
            ->map(function (?string $code) {
                return preg_match('/^OC(\d{5,})$/', (string) $code, $matches)
                    ? (int) $matches[1]
                    : 0;
            })
            ->max() ?? 0;

        do {
            $lastNumber++;
            $code = 'OC' . str_pad($lastNumber, 5, '0', STR_PAD_LEFT);
        } while (
            SupplierPurchaseOrder::withTrashed()
                ->where('code', $code)
                ->exists()
        );

        return $code;
    }

    private function generateSupplierPurchaseOrderPdf(SupplierPurchaseOrder $order): array
    {
        $fileName = 'orden_compra_proveedor_' . $this->sanitizeFileName($order->code) . '.pdf';
        $storedPath = 'supplier-purchase-orders/' . $fileName;

        $pdf = Pdf::loadView('admin.supplier-purchase-orders.pdf', [
            'order' => $order,
            'logoUrl' => $this->supplierOrderLogoUrl(),
        ])
            ->setPaper('a4', 'landscape')
            ->setOption(['isRemoteEnabled' => true]);

        Storage::disk('public')->put($storedPath, $pdf->output());

        $this->deletePreviousGeneratedSupplierPurchaseOrderPdfs($order, $storedPath);

        $document = Document::create([
            'documentable_type' => SupplierPurchaseOrder::class,
            'documentable_id' => $order->id,
            'document_type_id' => null,
            'original_name' => $fileName,
            'stored_name' => $fileName,
            'file_path' => $storedPath,
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'file_size' => Storage::disk('public')->size($storedPath) ?: 0,
            'issue_date' => now()->toDateString(),
            'expiration_date' => null,
            'observation' => 'PDF_GENERATED_SUPPLIER_PURCHASE_ORDER',
            'status' => 'ACTIVE',
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        return [
            'path' => $storedPath,
            'url' => Storage::disk('public')->url($storedPath),
            'document' => $document,
        ];
    }

    private function deletePreviousGeneratedSupplierPurchaseOrderPdfs(
        SupplierPurchaseOrder $order,
        string $currentPath
    ): void {
        $order->documents()
            ->where('observation', 'PDF_GENERATED_SUPPLIER_PURCHASE_ORDER')
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

    private function supplierOrderLogoUrl(): ?string
    {
        $logoPath = public_path('vendor/adminlte/dist/img/logo_img.png');

        return file_exists($logoPath)
            ? url('vendor/adminlte/dist/img/logo_img.png')
            : null;
    }

    private function statusPresentation(): array
    {
        return [
            self::STATUS_DRAFT => [
                'label' => 'Borrador',
                'class' => 'badge-secondary text-white',
                'icon' => 'fas fa-pencil-alt',
            ],
            self::STATUS_SENT => [
                'label' => 'Enviada al proveedor',
                'class' => 'badge-info text-white',
                'icon' => 'fas fa-paper-plane',
            ],
            self::STATUS_APPROVED => [
                'label' => 'Aprobada',
                'class' => 'badge-success text-white',
                'icon' => 'fas fa-check-circle',
            ],
            self::STATUS_RECEIVED => [
                'label' => 'Recibida',
                'class' => 'badge-primary text-white',
                'icon' => 'fas fa-box',
            ],
            self::STATUS_CANCELLED => [
                'label' => 'Cancelada',
                'class' => 'badge-danger text-white',
                'icon' => 'fas fa-times-circle',
            ],
            self::STATUS_INVOICED => [
                'label' => 'Facturada',
                'class' => 'badge-warning text-dark',
                'icon' => 'fas fa-file-invoice-dollar',
            ],
        ];
    }

    private function transportTypeOptions(): array
    {
        return [
            'terrestre',
            'aereo',
        ];
    }

    private function paymentConditionOptions(): array
    {
        return [
            'contado',
            'credito_20_dias',
            'credito_30_dias',
            'credito_45_dias',
            'credito_60_dias',
        ];
    }

    private function deliveryTypeOptions(): array
    {
        return [
            'agencia',
            'recojo_almacen',
            'transportista_proveedor',
        ];
    }

    private function paymentMethodOptions(): array
    {
        return [
            'efectivo',
            'tarjeta',
            'deposito_cuenta',
        ];
    }

    private function documentTypeOptions(): array
    {
        return [
            'factura',
            'boleta',
            'nota_pedido',
            'guia_remision',
        ];
    }

    private function upperOrNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? mb_strtoupper($value) : null;
    }
}
