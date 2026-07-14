<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Bank;
use App\Models\Brand;
use App\Models\Company;
use App\Models\Currency;
use App\Models\CustomerPurchaseOrder;
use App\Models\CustomerPurchaseOrderItem;
use App\Models\Document;
use App\Models\Presentation;
use App\Models\ShippingAgency;
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
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class SupplierPurchaseOrderController extends Controller
{
    private const STATUS_REGISTERED = 'registered';
    private const STATUS_SENT = 'sent';
    private const STATUS_APPROVED = 'approved';
    private const STATUS_RECEIVED = 'received';
    private const STATUS_PARTIAL_ENTERED = 'partial_entered';
    private const STATUS_ENTERED = 'entered';
    private const STATUS_CANCELLED = 'cancelled';
    private const STATUS_INVOICED = 'invoiced';

    public function __construct()
    {
        $this->middleware('can:admin.supplier-purchase-orders.index')->only(['index', 'list', 'generateCode', 'supplierAccounts', 'customerPurchaseOrderItems']);
        $this->middleware('can:admin.supplier-purchase-orders.load-items')->only(['loadCustomerOrderItems']);
        $this->middleware('can:admin.supplier-purchase-orders.store')->only(['store']);
        $this->middleware('can:admin.supplier-purchase-orders.update')->only(['update']);
        $this->middleware('can:admin.supplier-purchase-orders.destroy')->only(['destroy']);
        $this->middleware('can:admin.supplier-purchase-orders.show')->only(['show']);
    }

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
            ->with('bank:id,description,short_name', 'currency:id,code')
            ->where('status', 'ACTIVE')
            ->orderBy('account_number')
            ->get();

        $currencies = Currency::query()
            ->where('status', 'ACTIVE')
            ->orderBy('description')
            ->get();

        $banks = Bank::query()
            ->where('status', 'ACTIVE')
            ->orderBy('description')
            ->get(['id', 'description', 'short_name']);

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

        $shippingAgencies = ShippingAgency::query()
            ->where('status', 'ACTIVE')
            ->orderBy('business_name')
            ->get(['id', 'code', 'ruc', 'business_name', 'trade_name']);

        return view('admin.supplier-purchase-orders.index', compact(
            'companies',
            'suppliers',
            'supplierAccounts',
            'currencies',
            'banks',
            'customerPurchaseOrders',
            'articles',
            'units',
            'presentations',
            'brands',
            'ubigeos',
            'shippingAgencies'
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

    public function generateCode(Request $request)
    {
        $supplierAccountId = $request->input('supplier_account_id');

        if (!$supplierAccountId) {
            return response()->json([
                'code' => '',
                'message' => 'Seleccione una cuenta bancaria para generar el numero de orden.',
            ]);
        }

        $account = SupplierAccount::query()
            ->with('bank')
            ->find($supplierAccountId);

        if (!$account) {
            return response()->json([
                'message' => 'La cuenta bancaria seleccionada no existe.',
            ], 422);
        }

        $sequence = $this->nextPurchaseOrderSequence($account);

        return response()->json([
            'code' => $sequence['code'],
            'sequence' => $sequence['sequence'],
            'year' => $sequence['year'],
            'bank_code' => $sequence['bank_code'],
        ]);
    }

    public function supplierAccounts(Supplier $supplier)
    {
        $accounts = $supplier->accounts()
            ->with('bank:id,description,short_name', 'currency:id,code')
            ->where('status', 'ACTIVE')
            ->orderBy('account_number')
            ->get();

        return response()->json(['accounts' => $accounts]);
    }

    public function customerPurchaseOrderItems(Request $request, CustomerPurchaseOrder $customerPurchaseOrder)
    {
        $validated = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
        ]);

        return $this->customerPurchaseOrderItemsResponse(
            collect([$customerPurchaseOrder]),
            (int) $validated['supplier_id']
        );
    }

    public function loadCustomerOrderItems(Request $request)
    {
        $validated = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'customer_purchase_order_ids' => ['required', 'array', 'min:1'],
            'supplier_purchase_order_id' => ['nullable', 'exists:supplier_purchase_orders,id'],
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

        return $this->customerPurchaseOrderItemsResponse(
            $orders,
            (int) $validated['supplier_id'],
            $validated['supplier_purchase_order_id'] ?? null
        );
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
            'shippingAgency',
            'shippingAgencyBranch',
            'shippingAgencyContact',
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
        $this->appendEntryProgress($supplierPurchaseOrder);

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
            $customerPurchaseOrderIds = $this->customerPurchaseOrderIdsForSupplierOrder($supplierPurchaseOrder);

            $supplierPurchaseOrder->delete();
            $this->refreshCustomerPurchaseOrderStatuses($customerPurchaseOrderIds);

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
                'required',
                Rule::exists('supplier_accounts', 'id')
                    ->where('supplier_id', $request->input('supplier_id'))
                    ->where('status', 'ACTIVE')
                    ->whereNull('deleted_at'),
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
            'delivery_type' => ['required', Rule::in($this->deliveryTypeOptions())],
            'transport_type' => ['nullable', Rule::in($this->transportTypeOptions())],
            'shipping_address' => ['nullable', 'string'],
            'shipping_agency_id' => [
                Rule::requiredIf(fn () => $this->deliveryRequiresShippingAgency($request->input('delivery_type'))),
                'nullable',
                Rule::exists('shipping_agencies', 'id')->where('status', 'ACTIVE'),
            ],
            'shipping_agency_branch_id' => [
                Rule::requiredIf(fn () => $this->deliveryRequiresShippingAgency($request->input('delivery_type'))),
                'nullable',
                Rule::exists('shipping_agency_branches', 'id')
                    ->where('shipping_agency_id', $request->input('shipping_agency_id'))
                    ->where('status', 'ACTIVE'),
            ],
            'shipping_agency_contact_id' => [
                'nullable',
                Rule::exists('shipping_agency_contacts', 'id')
                    ->where('shipping_agency_id', $request->input('shipping_agency_id'))
                    ->where('status', 'ACTIVE'),
            ],
            'shipping_reference' => ['nullable', 'string', 'max:255'],
            'destination_ubigeo_id' => ['nullable', 'exists:ubigeos,id'],
            'destination_text' => ['nullable', 'string', 'max:255'],
            'payment_method' => ['nullable', Rule::in($this->paymentMethodOptions())],
            'document_type' => ['nullable', Rule::in($this->documentTypeOptions())],
            'affect_igv' => ['nullable', 'boolean'],
            'observations' => ['nullable', 'string'],
            'request_department' => ['nullable', 'string', 'max:255'],
            'authorized_by_name' => ['nullable', 'string', 'max:255'],
            'authorized_by_position' => ['nullable', 'string', 'max:255'],
            'delivery_text' => ['nullable', 'string', 'max:255'],
            'purchase_instructions' => ['nullable', 'string'],
            'important_note' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in($this->statusValues())],
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
        ], [
            'company_id.required' => 'La empresa es obligatoria.',
            'supplier_id.required' => 'El proveedor es obligatorio.',
            'supplier_account_id.required' => 'Debe seleccionar o registrar una cuenta bancaria del proveedor.',
            'supplier_account_id.exists' => 'La cuenta bancaria debe pertenecer al proveedor y estar activa.',
            'currency_id.required' => 'La moneda es obligatoria.',
            'customer_purchase_order_ids.required' => 'Debe seleccionar al menos una orden de cliente.',
            'customer_purchase_order_ids.min' => 'Debe seleccionar al menos una orden de cliente.',
            'delivery_type.required' => 'El tipo de entrega es obligatorio.',
            'shipping_agency_id.required' => 'Debe seleccionar una agencia de envio.',
            'shipping_agency_id.exists' => 'No se pudo cargar la agencia de envio seleccionada.',
            'shipping_agency_branch_id.required' => 'Debe seleccionar una sede de agencia.',
            'shipping_agency_branch_id.exists' => 'La sede seleccionada no pertenece a la agencia de envio.',
            'shipping_agency_contact_id.exists' => 'El contacto seleccionado no pertenece a la agencia de envio.',
            'items.required' => 'Debe ingresar al menos un articulo.',
            'items.min' => 'Debe ingresar al menos un articulo.',
            'items.*.article_id.required' => 'Debe seleccionar un articulo.',
            'items.*.billing_name_snapshot.required' => 'La descripcion del articulo es obligatoria.',
            'items.*.quantity.required' => 'La cantidad es obligatoria.',
            'items.*.quantity.min' => 'La cantidad debe ser mayor a cero.',
            'items.*.unit_price.required' => 'El precio unitario es obligatorio.',
            'items.*.unit_price.min' => 'El precio debe ser mayor o igual a cero.',
        ]);

        if (! empty($validated['shipping_agency_contact_id'])) {
            $contactMatchesBranch = DB::table('shipping_agency_contacts')
                ->where('id', $validated['shipping_agency_contact_id'])
                ->where(function ($query) use ($validated) {
                    $query->whereNull('shipping_agency_branch_id')
                        ->orWhere('shipping_agency_branch_id', $validated['shipping_agency_branch_id'] ?? null);
                })
                ->exists();

            if (! $contactMatchesBranch) {
                throw ValidationException::withMessages([
                    'shipping_agency_contact_id' => 'El contacto seleccionado no pertenece a la sede de agencia.',
                ]);
            }
        }

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
                $previousCustomerOrderIds = $order
                    ? $this->customerPurchaseOrderIdsForSupplierOrder($order)
                    : collect();
                $affectIgv = (bool) ($validated['affect_igv'] ?? false);
                $validated['items'] = $this->applySupplierAwardDataToItems(
                    (int) $validated['supplier_id'],
                    $customerOrderIds,
                    $validated['items']
                );
                $this->validateCustomerOrderItemPendingQuantities(
                    $validated['items'],
                    $order?->id
                );
                $preparedItems = $this->prepareItems($validated['items'], $affectIgv);
                $totals = $this->calculateTotals($preparedItems);
                $isAgencyDelivery = $this->deliveryRequiresShippingAgency($validated['delivery_type'] ?? null);
                $supplierAccount = SupplierAccount::query()
                    ->with('bank')
                    ->findOrFail($validated['supplier_account_id']);

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
                    'shipping_agency_id' => $isAgencyDelivery ? ($validated['shipping_agency_id'] ?? null) : null,
                    'shipping_agency_branch_id' => $isAgencyDelivery ? ($validated['shipping_agency_branch_id'] ?? null) : null,
                    'shipping_agency_contact_id' => $isAgencyDelivery ? ($validated['shipping_agency_contact_id'] ?? null) : null,
                    'shipping_reference' => $isAgencyDelivery
                        ? $this->upperOrNull($validated['shipping_reference'] ?? null)
                        : null,
                    'destination_ubigeo_id' => $validated['destination_ubigeo_id'] ?? null,
                    'destination_text' => $this->upperOrNull(
                        $validated['destination_text'] ?? null
                    ),
                    'payment_method' => $validated['payment_method'] ?? null,
                    'document_type' => $validated['document_type'] ?? null,
                    'affect_igv' => $affectIgv,
                    'observations' => $this->upperOrNull($validated['observations'] ?? null),
                    'request_department' => $this->upperOrNull($validated['request_department'] ?? null),
                    'authorized_by_name' => $this->upperOrNull($validated['authorized_by_name'] ?? null),
                    'authorized_by_position' => $this->upperOrNull($validated['authorized_by_position'] ?? null),
                    'delivery_text' => $this->upperOrNull($validated['delivery_text'] ?? null),
                    'purchase_instructions' => $this->buildPurchaseInstructionsText(
                        $supplierAccount,
                        $validated['destination_text'] ?? null,
                        $validated['destination_ubigeo_id'] ?? null
                    ),
                    'important_note' => $this->upperOrNull($validated['important_note'] ?? null),
                    'subtotal' => $totals['subtotal'],
                    'igv' => $totals['igv'],
                    'grand_total' => $totals['grand_total'],
                    'status' => $order
                        ? ($validated['status'] ?? (
                            $order->status === 'draft'
                                ? self::STATUS_REGISTERED
                                : $order->status
                        ))
                        : ($validated['status'] ?? self::STATUS_REGISTERED),
                    'updated_by' => Auth::id(),
                ];

                if ($order) {
                    $order->update($orderData);
                    $order->items()->delete();
                } else {
                    $sequence = $this->nextPurchaseOrderSequence($supplierAccount);
                    $orderData['code'] = $sequence['code'];
                    $orderData['purchase_order_sequence'] = $sequence['sequence'];
                    $orderData['purchase_order_year'] = $sequence['year'];
                    $orderData['purchase_order_bank_code'] = $sequence['bank_code'];
                    $orderData['created_by'] = Auth::id();
                    $order = SupplierPurchaseOrder::create($orderData);
                }

                $wasRecentlyCreated = $order->wasRecentlyCreated;

                foreach ($preparedItems as $item) {
                    $order->items()->create($item);
                }

                $order->customerPurchaseOrders()->sync($customerOrderIds);
                $order->refreshEntryStatus();
                $this->refreshCustomerPurchaseOrderStatuses(
                    $previousCustomerOrderIds
                        ->merge($customerOrderIds)
                        ->unique()
                        ->values()
                );

                try {
                    $pdfData = $this->generateSupplierPurchaseOrderPdf($order->fresh([
                        'company',
                        'supplier',
                        'supplierAccount.bank',
                        'supplierAccount.currency',
                        'currency',
                        'destinationUbigeo',
                        'shippingAgency',
                        'shippingAgencyBranch',
                        'shippingAgencyContact',
                        'creator',
                        'updater',
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
        } catch (ValidationException $e) {
            throw $e;
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

    private function customerPurchaseOrderItemsResponse(
        $orders,
        ?int $supplierId = null,
        $supplierPurchaseOrderId = null
    )
    {
        $orders->each(function (CustomerPurchaseOrder $order) {
            $order->load([
                'customer:id,business_name,full_name,first_name,last_name',
                'items.quoteItem',
                'items.article',
                'items.unit',
                'items.presentation',
                'items.brand',
            ]);
        });

        $firstOrder = $orders->first();
        $awardMap = $supplierId
            ? $this->awardedQuoteItemsForSupplier($orders, $supplierId)
            : collect();
        $purchaseProgress = $this->customerOrderItemPurchaseProgress(
            $orders
                ->flatMap(fn (CustomerPurchaseOrder $order) => $order->items)
                ->pluck('id')
                ->all(),
            $supplierPurchaseOrderId
        );

        $items = $orders->flatMap(function (CustomerPurchaseOrder $order) {
            $customerName = $order->customer?->business_name
                ?? $order->customer?->full_name
                ?? trim(
                    ($order->customer?->first_name ?? '') . ' ' .
                    ($order->customer?->last_name ?? '')
                )
                ?: null;

            return $order->items->map(function (CustomerPurchaseOrderItem $item) use ($order, $customerName) {
                return [
                    'item' => $item,
                    'order' => $order,
                    'customer_name' => $customerName,
                ];
            });
        })
            ->filter(function (array $row) use ($awardMap, $supplierId, $purchaseProgress) {
                /** @var CustomerPurchaseOrderItem $item */
                $item = $row['item'];

                if (strtolower((string) $item->status) === 'deleted') {
                    return false;
                }

                if (!$supplierId) {
                    return true;
                }

                $marketStudyItemId = $item->market_study_item_id
                    ?? $item->quoteItem?->market_study_item_id;

                if ($marketStudyItemId && ! $awardMap->has((int) $marketStudyItemId)) {
                    return false;
                }

                return (float) ($purchaseProgress[$item->id]['pending_quantity'] ?? 0) > 0;
            })
            ->map(function (array $row) use ($awardMap, $supplierId, $purchaseProgress) {
                /** @var CustomerPurchaseOrderItem $item */
                $item = $row['item'];
                /** @var CustomerPurchaseOrder $order */
                $order = $row['order'];
                $customerName = $row['customer_name'];
                $marketStudyItemId = $item->market_study_item_id
                    ?? $item->quoteItem?->market_study_item_id;
                $award = $supplierId && $marketStudyItemId
                    ? $awardMap->get((int) $marketStudyItemId)
                    : null;
                $progress = $purchaseProgress[$item->id] ?? [
                    'requested_quantity' => round((float) $item->quantity, 2),
                    'purchased_quantity' => 0,
                    'pending_quantity' => round((float) $item->quantity, 2),
                ];
                $payload = $this->sourceItemPayload(
                    $item,
                    'customer_purchase_order_item_id',
                    $award
                );

                if (! $award) {
                    $payload['unit_price'] = 0;
                    $payload['reference_purchase_price'] = 0;
                }

                $payload['quantity'] = $progress['pending_quantity'];

                return array_merge(
                    $payload,
                    [
                        'customer_purchase_order_id' => $order->id,
                        'customer_purchase_order_code' => $order->code,
                        'customer_order_code' => $order->code,
                        'customer_name' => $customerName,
                        'article_name' => $payload['billing_name_snapshot'] ?? null,
                        'unit_name' => $item->unit?->abbreviation ?? $item->unit?->description,
                        'presentation_name' => $item->presentation?->description,
                        'brand_name' => $item->brand?->description,
                        'requested_quantity' => $progress['requested_quantity'],
                        'purchased_quantity' => $progress['purchased_quantity'],
                        'pending_quantity' => $progress['pending_quantity'],
                        'suggested_quantity' => $progress['pending_quantity'],
                    ]
                );
            })
            ->values();

        return response()->json([
            'customer_purchase_order_ids' => $orders->pluck('id')->values(),
            'company_id' => $firstOrder?->company_id,
            'currency_id' => $firstOrder?->currency_id,
            'quote_id' => $firstOrder?->quote_id,
            'supplier_id' => $supplierId,
            'items' => $items,
        ]);
    }

    private function customerOrderItemPurchaseProgress(array $customerOrderItemIds, $excludeSupplierPurchaseOrderId = null): array
    {
        $ids = collect($customerOrderItemIds)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $requested = CustomerPurchaseOrderItem::query()
            ->whereIn('id', $ids)
            ->pluck('quantity', 'id')
            ->map(fn ($quantity) => round((float) $quantity, 2));

        $purchasedQuery = DB::table('supplier_purchase_order_items as items')
            ->join('supplier_purchase_orders as orders', 'orders.id', '=', 'items.supplier_purchase_order_id')
            ->whereIn('items.customer_purchase_order_item_id', $ids)
            ->whereNull('orders.deleted_at')
            ->where('orders.status', '!=', self::STATUS_CANCELLED)
            ->where('items.status', '!=', 'deleted');

        if ($excludeSupplierPurchaseOrderId) {
            $purchasedQuery->where('orders.id', '!=', (int) $excludeSupplierPurchaseOrderId);
        }

        $purchased = $purchasedQuery
            ->groupBy('items.customer_purchase_order_item_id')
            ->selectRaw('items.customer_purchase_order_item_id, SUM(items.quantity) as purchased_quantity')
            ->pluck('purchased_quantity', 'customer_purchase_order_item_id')
            ->map(fn ($quantity) => round((float) $quantity, 2));

        return $ids
            ->mapWithKeys(function (int $id) use ($requested, $purchased) {
                $requestedQuantity = round((float) ($requested[$id] ?? 0), 2);
                $purchasedQuantity = round((float) ($purchased[$id] ?? 0), 2);

                return [
                    $id => [
                        'requested_quantity' => $requestedQuantity,
                        'purchased_quantity' => $purchasedQuantity,
                        'pending_quantity' => max(round($requestedQuantity - $purchasedQuantity, 2), 0),
                    ],
                ];
            })
            ->all();
    }

    private function validateCustomerOrderItemPendingQuantities(array $items, ?int $currentSupplierPurchaseOrderId = null): void
    {
        $customerOrderItemIds = collect($items)
            ->pluck('customer_purchase_order_item_id')
            ->filter()
            ->all();

        if (empty($customerOrderItemIds)) {
            return;
        }

        $progress = $this->customerOrderItemPurchaseProgress(
            $customerOrderItemIds,
            $currentSupplierPurchaseOrderId
        );

        $requestedByCustomerItem = collect($items)
            ->filter(fn (array $item) => ! empty($item['customer_purchase_order_item_id']))
            ->groupBy(fn (array $item) => (int) $item['customer_purchase_order_item_id'])
            ->map(fn ($group) => round((float) $group->sum(fn (array $item) => (float) ($item['quantity'] ?? 0)), 2));

        foreach ($items as $index => $item) {
            $customerOrderItemId = $item['customer_purchase_order_item_id'] ?? null;

            if (! $customerOrderItemId) {
                continue;
            }

            $quantity = round((float) ($requestedByCustomerItem[(int) $customerOrderItemId] ?? 0), 2);
            $pending = round((float) ($progress[(int) $customerOrderItemId]['pending_quantity'] ?? 0), 2);

            if ($quantity > $pending) {
                throw ValidationException::withMessages([
                    "items.$index.quantity" => 'La cantidad a comprar supera el saldo pendiente de la orden del cliente.',
                ]);
            }
        }
    }

    private function awardedQuoteItemsForSupplier($orders, int $supplierId)
    {
        $marketStudyItemIds = $orders
            ->flatMap(fn (CustomerPurchaseOrder $order) => $order->items)
            ->map(fn (CustomerPurchaseOrderItem $item) => $item->market_study_item_id
                ?? $item->quoteItem?->market_study_item_id)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($marketStudyItemIds->isEmpty()) {
            return collect();
        }

        return DB::table('market_study_item_winners as winners')
            ->join(
                'market_study_quote_items as quote_items',
                'quote_items.id',
                '=',
                'winners.market_study_quote_item_id'
            )
            ->join(
                'market_study_quotes as quotes',
                'quotes.id',
                '=',
                'quote_items.market_study_quote_id'
            )
            ->where('quotes.supplier_id', $supplierId)
            ->whereIn('winners.market_study_item_id', $marketStudyItemIds)
            ->whereNull('quote_items.deleted_at')
            ->whereNull('quotes.deleted_at')
            ->select([
                'winners.market_study_item_id',
                'winners.market_study_quote_item_id',
                'quote_items.article_id',
                'quote_items.brand_id',
                'quote_items.unit_id',
                'quote_items.presentation_id',
                'quote_items.origin',
                'quote_items.expiration_date',
                'quote_items.quantity',
                'quote_items.unit_price',
                'quote_items.subtotal',
                'quote_items.tax_amount',
                'quote_items.total',
            ])
            ->get()
            ->keyBy(fn ($row) => (int) $row->market_study_item_id);
    }

    private function applySupplierAwardDataToItems(
        int $supplierId,
        array $customerOrderIds,
        array $items
    ): array {
        $customerOrderItems = CustomerPurchaseOrderItem::query()
            ->with('quoteItem')
            ->whereIn(
                'id',
                collect($items)
                    ->pluck('customer_purchase_order_item_id')
                    ->filter()
                    ->all()
            )
            ->get()
            ->keyBy('id');

        $orders = CustomerPurchaseOrder::query()
            ->with('items.quoteItem')
            ->whereIn('id', $customerOrderIds)
            ->get();

        $awardMap = $this->awardedQuoteItemsForSupplier($orders, $supplierId);

        foreach ($items as $index => $item) {
            $customerItemId = $item['customer_purchase_order_item_id'] ?? null;

            $customerItem = null;

            if ($customerItemId) {
                $customerItem = $customerOrderItems->get($customerItemId);

                if (!$customerItem) {
                    throw ValidationException::withMessages([
                        "items.$index.customer_purchase_order_item_id" =>
                        'El item de orden cliente no existe.',
                    ]);
                }

                if (!in_array((int) $customerItem->customer_purchase_order_id, $customerOrderIds, true)) {
                    throw ValidationException::withMessages([
                        "items.$index.customer_purchase_order_item_id" =>
                        'El item no pertenece a las ordenes cliente seleccionadas.',
                    ]);
                }
            }

            $marketStudyItemId = $item['market_study_item_id']
                ?? $customerItem?->market_study_item_id
                ?? $customerItem?->quoteItem?->market_study_item_id;

            if (!$customerItemId && !$marketStudyItemId) {
                continue;
            }

            if (!$marketStudyItemId) {
                continue;
            }

            if (!$awardMap->has((int) $marketStudyItemId)) {
                throw ValidationException::withMessages([
                    "items.$index.article_id" =>
                    'El item no esta adjudicado al proveedor seleccionado.',
                ]);
            }

            $award = $awardMap->get((int) $marketStudyItemId);
            $winnerPrice = round((float) ($award->unit_price ?? 0), 2);

            $items[$index]['market_study_item_id'] = (int) $marketStudyItemId;
            $items[$index]['unit_price'] = $winnerPrice;
            $items[$index]['reference_purchase_price'] = $winnerPrice;

            if (!empty($award->article_id)) {
                $items[$index]['article_id'] = $award->article_id;
            }

            $items[$index]['brand_id'] = $award->brand_id ?? ($items[$index]['brand_id'] ?? null);
            $items[$index]['unit_id'] = $award->unit_id ?? ($items[$index]['unit_id'] ?? null);
            $items[$index]['presentation_id'] = $award->presentation_id ?? ($items[$index]['presentation_id'] ?? null);
            $items[$index]['origin'] = $award->origin ?? ($items[$index]['origin'] ?? null);
            $items[$index]['expiration_date'] = $award->expiration_date ?? ($items[$index]['expiration_date'] ?? null);
        }

        return $items;
    }

    private function sourceItemPayload(
        Model $item,
        string $sourceKey,
        ?object $award = null
    ): array
    {
        $article = $item->article;
        $quantity = round((float) ($item->quantity ?? 1), 2);
        $unitPrice = round(
            (float) ($award->unit_price ?? $item->unit_price ?? $item->cost_price ?? 0),
            2
        );

        return [
            $sourceKey => $item->id,
            'article_id' => $award->article_id ?? $item->article_id,
            'market_study_item_id' => $award->market_study_item_id
                ?? $item->market_study_item_id
                ?? $item->quoteItem?->market_study_item_id
                ?? null,
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
            'unit_id' => $award->unit_id ?? $item->unit_id ?? $article?->unit_id,
            'presentation_id' => $award->presentation_id ?? $item->presentation_id ?? $article?->presentation_id,
            'brand_id' => $award->brand_id ?? $item->brand_id ?? $article?->brand_id,
            'origin' => $award->origin ?? $item->origin ?? null,
            'expiration_date' => ($award->expiration_date ?? $item->expiration_date)
                ? (string) ($award->expiration_date ?? $item->expiration_date)
                : null,
            'cost_type' => $item->cost_type ?? $item->cost_condition_snapshot ?? 'PESO',
            'reference_purchase_price' => $unitPrice,
            'quantity' => $quantity > 0 ? $quantity : 1,
            'unit_price' => $unitPrice,
        ];
    }

    private function nextPurchaseOrderSequence(SupplierAccount $account): array
    {
        $year = (int) now()->year;
        $bankCode = $this->bankCodeForPurchaseOrder($account);

        $lastSequence = SupplierPurchaseOrder::withTrashed()
            ->where('purchase_order_year', $year)
            ->where('purchase_order_bank_code', $bankCode)
            ->max('purchase_order_sequence') ?? 0;

        do {
            $lastSequence++;
            $code = str_pad((string) $lastSequence, 5, '0', STR_PAD_LEFT) . '-' . $year . '-' . $bankCode;
        } while (SupplierPurchaseOrder::withTrashed()->where('code', $code)->exists());

        return [
            'code' => $code,
            'sequence' => $lastSequence,
            'year' => $year,
            'bank_code' => $bankCode,
        ];
    }

    private function bankCodeForPurchaseOrder(SupplierAccount $account): string
    {
        $bank = $account->bank;
        $rawCode = $bank?->short_name ?: $bank?->description;

        if (!$rawCode) {
            throw ValidationException::withMessages([
                'supplier_account_id' => 'El banco seleccionado no tiene codigo configurado.',
            ]);
        }

        $normalized = mb_strtoupper(Str::ascii(trim($rawCode)));
        $compactNormalized = preg_replace('/[^A-Z0-9]+/', '', $normalized);

        foreach (['BBVA', 'BCP', 'INTERBANK', 'SCOTIABANK'] as $knownBankCode) {
            if (str_contains($compactNormalized, $knownBankCode)) {
                return $knownBankCode;
            }
        }

        $normalized = str_replace(
            ['BANCO DE CREDITO DEL PERU', 'BANCO DE CREDITO', 'CREDITO DEL PERU', 'BCP'],
            'BCP',
            $normalized
        );
        $normalized = str_replace(['BBVA CONTINENTAL', 'BANCO BBVA', 'BBVA'], 'BBVA', $normalized);
        $normalized = str_replace(['INTERBANK', 'BANCO INTERNACIONAL DEL PERU'], 'INTERBANK', $normalized);
        $normalized = str_replace(['SCOTIABANK PERU', 'SCOTIABANK'], 'SCOTIABANK', $normalized);
        $normalized = str_replace(['BANCO DE LA NACION', 'BANCO NACION'], 'BANCO_NACION', $normalized);
        $normalized = preg_replace('/[^A-Z0-9_]+/', '_', $normalized);
        $normalized = trim((string) $normalized, '_');

        return $normalized !== '' ? $normalized : 'SINBANCO';
    }

    private function generateSupplierPurchaseOrderPdf(SupplierPurchaseOrder $order): array
    {
        $fileName = 'orden_compra_proveedor_' . $this->sanitizeFileName($order->code) . '.pdf';
        $storedPath = 'supplier-purchase-orders/' . $fileName;

        $pdf = Pdf::loadView('admin.supplier-purchase-orders.pdf', [
            'order' => $order,
            'logoUrl' => $this->supplierOrderLogoUrl(),
        ])
            ->setPaper('a4', 'portrait')
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
            self::STATUS_REGISTERED => [
                'label' => 'Registrado',
                'class' => 'badge-primary text-white',
                'icon' => 'fas fa-clipboard-check',
            ],
            'draft' => [
                'label' => 'Registrado',
                'class' => 'badge-primary text-white',
                'icon' => 'fas fa-clipboard-check',
            ],
            self::STATUS_SENT => [
                'label' => 'Enviado',
                'class' => 'badge-info text-white',
                'icon' => 'fas fa-paper-plane',
            ],
            self::STATUS_APPROVED => [
                'label' => 'Aprobado',
                'class' => 'badge-success text-white',
                'icon' => 'fas fa-check-circle',
            ],
            self::STATUS_RECEIVED => [
                'label' => 'Ingresado',
                'class' => 'badge-success text-white',
                'icon' => 'fas fa-box',
            ],
            self::STATUS_PARTIAL_ENTERED => [
                'label' => 'Ingreso parcial',
                'class' => 'badge-warning text-dark',
                'icon' => 'fas fa-hourglass-half',
            ],
            self::STATUS_ENTERED => [
                'label' => 'Ingresado',
                'class' => 'badge-success text-white',
                'icon' => 'fas fa-warehouse',
            ],
            self::STATUS_CANCELLED => [
                'label' => 'Cancelado',
                'class' => 'badge-danger text-white',
                'icon' => 'fas fa-times-circle',
            ],
            self::STATUS_INVOICED => [
                'label' => 'Facturado',
                'class' => 'badge-info text-white',
                'icon' => 'fas fa-file-invoice-dollar',
            ],
        ];
    }

    private function statusValues(): array
    {
        return [
            self::STATUS_REGISTERED,
            self::STATUS_SENT,
            self::STATUS_APPROVED,
            self::STATUS_RECEIVED,
            self::STATUS_PARTIAL_ENTERED,
            self::STATUS_ENTERED,
            self::STATUS_CANCELLED,
            self::STATUS_INVOICED,
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
            'agencia_transporte',
            'en_agencia',
            'transporte',
            'recojo_almacen',
            'transportista_proveedor',
        ];
    }

    private function deliveryRequiresShippingAgency(?string $deliveryType): bool
    {
        $normalized = mb_strtolower(Str::ascii(trim((string) $deliveryType)));
        $normalized = str_replace(['.', '-'], '', $normalized);
        $normalized = preg_replace('/\s+/', '_', $normalized);

        $aliases = [
            'agencia_de_transporte' => 'agencia_transporte',
        ];

        return in_array($aliases[$normalized] ?? $normalized, [
            'agencia',
            'agencia_transporte',
            'en_agencia',
            'transporte',
        ], true);
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

    private function buildPurchaseInstructionsText(
        ?SupplierAccount $account,
        ?string $destinationText,
        $destinationUbigeoId
    ): string {
        $bank = $this->purchaseInstructionBankName($account);
        $destination = $this->purchaseInstructionDestination($destinationText, $destinationUbigeoId);

        return sprintf(
            'Abono de la presente Orden de compra se realizo a cuentas de la empresa %s - Factura enviar al correo, embalaje y rotulado de forma correcta, para ser enviado a la ciudad de %s',
            $bank,
            $destination ?: '-'
        );
    }

    private function purchaseInstructionBankName(?SupplierAccount $account): string
    {
        $rawBank = $account?->bank?->short_name
            ?: $account?->bank?->description
            ?: '';
        $normalizedBank = mb_strtoupper(Str::ascii(trim($rawBank)));
        $compactBank = preg_replace('/[^A-Z0-9]+/', '', $normalizedBank);

        foreach (['BBVA', 'BCP', 'INTERBANK', 'SCOTIABANK'] as $knownBankCode) {
            if (str_contains((string) $compactBank, $knownBankCode)) {
                return $knownBankCode;
            }
        }

        $normalizedBank = preg_replace('/[^A-Z0-9 ]+/', ' ', $normalizedBank);

        return trim((string) preg_replace('/\s+/', ' ', $normalizedBank));
    }

    private function purchaseInstructionDestination(?string $destinationText, $destinationUbigeoId): string
    {
        $optionalDestination = trim((string) $destinationText);

        if ($optionalDestination !== '') {
            return mb_strtoupper(Str::ascii($optionalDestination));
        }

        if (! $destinationUbigeoId) {
            return '';
        }

        $ubigeo = Ubigeo::query()->find($destinationUbigeoId);

        if (! $ubigeo) {
            return '';
        }

        return mb_strtoupper(Str::ascii(
            collect([$ubigeo->department, $ubigeo->district])
                ->filter()
                ->unique()
                ->join(' / ')
        ));
    }

    private function appendEntryProgress(SupplierPurchaseOrder $order): void
    {
        $itemIds = $order->items
            ->where('status', '!=', 'deleted')
            ->pluck('id')
            ->all();

        if (empty($itemIds)) {
            return;
        }

        $receivedByItem = DB::table('warehouse_entry_items as items')
            ->join('warehouse_entries as entries', 'entries.id', '=', 'items.warehouse_entry_id')
            ->where('entries.supplier_purchase_order_id', $order->id)
            ->whereNull('entries.deleted_at')
            ->where('entries.status', 'registered')
            ->whereIn('items.supplier_purchase_order_item_id', $itemIds)
            ->where('items.status', '!=', 'deleted')
            ->groupBy('items.supplier_purchase_order_item_id')
            ->selectRaw('items.supplier_purchase_order_item_id, SUM(items.quantity) as received_quantity')
            ->pluck('received_quantity', 'supplier_purchase_order_item_id');

        $order->items->each(function (SupplierPurchaseOrderItem $item) use ($receivedByItem) {
            $ordered = round((float) $item->quantity, 2);
            $received = round((float) ($receivedByItem[$item->id] ?? 0), 2);
            $pending = max(round($ordered - $received, 2), 0);
            $status = match (true) {
                $received <= 0 => 'pending',
                $pending <= 0 => 'entered',
                default => 'partial_entered',
            };

            $item->setAttribute('ordered_quantity', $ordered);
            $item->setAttribute('entered_quantity', $received);
            $item->setAttribute('pending_quantity', $pending);
            $item->setAttribute('entry_status', $status);
        });
    }

    private function customerPurchaseOrderIdsForSupplierOrder(SupplierPurchaseOrder $order)
    {
        $pivotIds = DB::table('supplier_purchase_order_customer_purchase_order')
            ->where('supplier_purchase_order_id', $order->id)
            ->pluck('customer_purchase_order_id');

        $itemIds = DB::table('supplier_purchase_order_items as supplier_items')
            ->join('customer_purchase_order_items as customer_items', 'customer_items.id', '=', 'supplier_items.customer_purchase_order_item_id')
            ->where('supplier_items.supplier_purchase_order_id', $order->id)
            ->where('supplier_items.status', '!=', 'deleted')
            ->pluck('customer_items.customer_purchase_order_id');

        return $pivotIds
            ->merge($itemIds)
            ->merge([$order->customer_purchase_order_id])
            ->filter()
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
}
