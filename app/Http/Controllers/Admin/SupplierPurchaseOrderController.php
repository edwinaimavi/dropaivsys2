<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Bank;
use App\Models\Brand;
use App\Models\Company;
use App\Models\CompanyBankAccount;
use App\Models\Currency;
use App\Models\CustomerPurchaseOrder;
use App\Models\CustomerPurchaseOrderItem;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Presentation;
use App\Models\ShippingAgency;
use App\Models\Supplier;
use App\Models\SupplierAccount;
use App\Models\SupplierPurchaseOrder;
use App\Models\SupplierPurchaseOrderAdvancePayment;
use App\Models\SupplierPurchaseOrderItem;
use App\Models\Ubigeo;
use App\Models\Unit;
use App\Services\BankMovementService;
use App\Services\CustomerPurchaseOrderStatusService;
use App\Services\SupplierPurchaseOrderFinancialService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class SupplierPurchaseOrderController extends Controller
{
    private const SUPPLIER_DOCUMENT_TYPES = [
        'supplier_quote',
        'payment_support',
        'other',
    ];

    private const SUPPLIER_DOCUMENT_TYPE_CODES = [
        'SPO_QUOTE',
        'SPO_PAYMENT_SUPPORT',
        'SPO_OTHER',
    ];

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
        $this->middleware('can:admin.supplier-purchase-orders.index')->only([
            'index', 'list', 'generateCode', 'supplierAccounts', 'companyBankAccounts',
            'customerPurchaseOrderItems',
        ]);
        $this->middleware('can:admin.supplier-purchase-orders.load-items')->only(['loadCustomerOrderItems']);
        $this->middleware('can:admin.supplier-purchase-orders.store')->only(['store']);
        $this->middleware('can:admin.supplier-purchase-orders.update')->only(['update', 'destroyDocument']);
        $this->middleware('can:admin.supplier-purchase-orders.destroy')->only(['destroy']);
        $this->middleware('can:admin.supplier-purchase-orders.show')->only([
            'show', 'viewDocument', 'viewAdvancePaymentProof',
        ]);
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
            ->availableForSupplierPurchase()
            ->with(
                'customer:id,business_name,full_name,first_name,last_name',
                'currency:id,code,symbol'
            )
            ->orderByDesc('id')
            ->get([
                'id',
                'code',
                'purchase_order_number',
                'customer_id',
                'currency_id',
                'grand_total',
                'status',
            ]);

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
                'paymentCurrency:id,code,symbol,description',
                'customerPurchaseOrder.customer:id,business_name,full_name,first_name,last_name',
                'customerPurchaseOrder.customerBranch:id,branch_name',
                'customerPurchaseOrder.company:id,business_name,trade_name',
                'customerPurchaseOrder.currency:id,code,symbol',
                'customerPurchaseOrders.customer:id,business_name,full_name,first_name,last_name',
                'customerPurchaseOrders.customerBranch:id,branch_name',
                'customerPurchaseOrders.company:id,business_name,trade_name',
                'customerPurchaseOrders.currency:id,code,symbol',
                'documents' => function ($query) {
                    $query->with('documentType:id,code,description')
                        ->where('status', 'ACTIVE')
                        ->orderByDesc('id');
                },
            ])
            ->orderByDesc('updated_at')
            ->orderByDesc('id');

        return DataTables::of($orders)
            ->addIndexColumn()
            ->editColumn('code', function (SupplierPurchaseOrder $order) {
                $code = e($order->code ?: '-');
                $pdfUrl = Auth::user()?->can('admin.supplier-purchase-orders.pdf')
                    ? $this->generatedPdfUrl($order)
                    : null;

                if (! $pdfUrl) {
                    return $code;
                }

                return sprintf(
                    '<a href="%s" target="_blank" rel="noopener" class="supplier-order-code-link" title="Ver PDF de la orden"><span class="supplier-order-code-icon"><i class="far fa-file-pdf" aria-hidden="true"></i></span><span>%s</span></a>',
                    e($pdfUrl),
                    $code
                );
            })
            ->addColumn('customer_order', function (SupplierPurchaseOrder $order) {
                $customerOrders = $this->customerOrdersForSupplierOrder($order);

                if ($customerOrders->isEmpty()) {
                    return '<span class="badge badge-light text-muted border">Sin OC cliente</span>';
                }

                return $customerOrders->unique('id')->map(function (CustomerPurchaseOrder $customerOrder) {
                    $number = $customerOrder->purchase_order_number ?: $customerOrder->code ?: '-';
                    $customer = $customerOrder->customer;
                    $customerName = $customer?->business_name
                        ?? $customer?->full_name
                        ?? trim(($customer?->first_name ?? '').' '.($customer?->last_name ?? ''))
                        ?: 'Sin cliente';
                    $branchName = $customerOrder->customerBranch?->branch_name ?: 'Sin sede registrada';

                    return sprintf(
                        '<div class="customer-order-cell"><span class="customer-order-number">%s</span><small class="customer-order-client">%s</small><small class="customer-order-branch">%s</small></div>',
                        e($number),
                        e($customerName),
                        e($branchName)
                    );
                })->implode('');
            })
            ->addColumn('customer_order_group_key', function (SupplierPurchaseOrder $order) {
                return (string) ($this->customerOrdersForSupplierOrder($order)->first()?->id ?? 'direct-purchases');
            })
            ->addColumn('customer_order_number', function (SupplierPurchaseOrder $order) {
                $customerOrder = $this->customerOrdersForSupplierOrder($order)->first();

                return $customerOrder?->purchase_order_number ?: $customerOrder?->code ?: 'Compras directas / Sin OC Cliente';
            })
            ->addColumn('customer_order_client', function (SupplierPurchaseOrder $order) {
                $customer = $this->customerOrdersForSupplierOrder($order)->first()?->customer;

                return $customer?->business_name
                    ?? $customer?->full_name
                    ?? trim(($customer?->first_name ?? '').' '.($customer?->last_name ?? ''))
                    ?: 'Sin cliente relacionado';
            })
            ->addColumn('customer_order_branch', function (SupplierPurchaseOrder $order) {
                $customerOrder = $this->customerOrdersForSupplierOrder($order)->first();

                return $customerOrder
                    ? ($customerOrder->customerBranch?->branch_name ?: 'Sin sede registrada')
                    : 'Sin OC Cliente vinculada';
            })
            ->addColumn('grand_total_value', fn (SupplierPurchaseOrder $order) => (float) $order->grand_total)
            ->addColumn('financial_summary', function (SupplierPurchaseOrder $order) {
                $purchase = $order->currency?->code ?? '-';
                $payment = $order->paymentCurrency?->code ?? $purchase;
                $rate = $order->apply_exchange_rate && $order->exchange_rate
                    ? ' | TC '.number_format((float) $order->exchange_rate, 4)
                    : '';

                return "{$purchase} → {$payment}{$rate}";
            })
            ->addColumn('advance_summary', function (SupplierPurchaseOrder $order) {
                if (! $order->apply_advance) {
                    return 'Sin anticipo';
                }

                return 'Anticipo: '.match ($order->advance_status) {
                    SupplierPurchaseOrder::ADVANCE_PAID => 'Pagado',
                    SupplierPurchaseOrder::ADVANCE_PARTIAL => 'Parcial',
                    default => 'Pendiente',
                };
            })
            ->addColumn('supplier_id_value', fn (SupplierPurchaseOrder $order) => $order->supplier_id)
            ->addColumn('status_code', fn (SupplierPurchaseOrder $order) => strtolower((string) $order->status))
            ->addColumn('group_date', fn (SupplierPurchaseOrder $order) => $order->updated_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '-')
            ->addColumn('supplier_name', function (SupplierPurchaseOrder $order) {
                return $order->supplier?->business_name
                    ?? $order->supplier?->short_name
                    ?? '-';
            })
            ->addColumn('supplier_has_quotation', function (SupplierPurchaseOrder $order) {
                return $order->supplierQuotationDocument() !== null;
            })
            ->addColumn('supplier_quotation_url', function (SupplierPurchaseOrder $order) {
                $quotation = $order->supplierQuotationDocument();

                if (! $quotation || ! Auth::user()?->can('admin.supplier-purchase-orders.show')) {
                    return null;
                }

                return route(
                    'admin.supplier-purchase-orders.documents.view',
                    [$order, $quotation]
                );
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

                return sprintf(
                    '<span class="supplier-order-total">%s</span>',
                    e(trim($symbol.' '.number_format((float) $order->grand_total, 2)))
                );
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
                return $order->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '-';
            })
            ->addColumn('acciones', function (SupplierPurchaseOrder $order) {
                $pdfUrl = $this->generatedPdfUrl($order);

                return view(
                    'admin.supplier-purchase-orders.partials.acciones',
                    compact('order', 'pdfUrl')
                )->render();
            })
            ->filterColumn('customer_order', function ($query, $keyword) {
                $query->where(function ($customerOrderQuery) use ($keyword) {
                    $customerOrderQuery
                        ->whereHas('customerPurchaseOrder', function ($orderQuery) use ($keyword) {
                            $this->applyCustomerOrderSearch($orderQuery, $keyword);
                        })
                        ->orWhereHas('customerPurchaseOrders', function ($orderQuery) use ($keyword) {
                            $this->applyCustomerOrderSearch($orderQuery, $keyword);
                        });
                });
            })
            ->filterColumn('supplier_name', function ($query, $keyword) {
                $query->whereHas('supplier', function ($supplierQuery) use ($keyword) {
                    $supplierQuery->where('business_name', 'like', "%{$keyword}%")
                        ->orWhere('short_name', 'like', "%{$keyword}%")
                        ->orWhere('ruc', 'like', "%{$keyword}%");
                });
            })
            ->filterColumn('company', function ($query, $keyword) {
                $query->whereHas('company', function ($companyQuery) use ($keyword) {
                    $companyQuery->where('business_name', 'like', "%{$keyword}%")
                        ->orWhere('trade_name', 'like', "%{$keyword}%");
                });
            })
            ->filterColumn('status', function ($query, $keyword) {
                $normalized = strtolower(trim($keyword));
                $aliases = [
                    'registrada' => self::STATUS_REGISTERED,
                    'registrado' => self::STATUS_REGISTERED,
                    'enviada' => self::STATUS_SENT,
                    'enviado' => self::STATUS_SENT,
                    'aprobada' => self::STATUS_APPROVED,
                    'aprobado' => self::STATUS_APPROVED,
                    'recibida' => self::STATUS_RECEIVED,
                    'recibido' => self::STATUS_RECEIVED,
                    'ingreso parcial' => self::STATUS_PARTIAL_ENTERED,
                    'ingresada' => self::STATUS_ENTERED,
                    'ingresado' => self::STATUS_ENTERED,
                    'anulada' => self::STATUS_CANCELLED,
                    'anulado' => self::STATUS_CANCELLED,
                    'facturada' => self::STATUS_INVOICED,
                    'facturado' => self::STATUS_INVOICED,
                ];

                $query->where('status', 'like', '%'.($aliases[$normalized] ?? $keyword).'%');
            })
            ->rawColumns(['code', 'customer_order', 'grand_total', 'status', 'acciones'])
            ->make(true);
    }

    private function customerOrdersForSupplierOrder(SupplierPurchaseOrder $order)
    {
        $customerOrders = $order->customerPurchaseOrders;

        if ($customerOrders->isEmpty() && $order->customerPurchaseOrder) {
            $customerOrders = collect([$order->customerPurchaseOrder]);
        }

        return $customerOrders->unique('id')->sortBy('id')->values();
    }

    private function generatedPdfUrl(SupplierPurchaseOrder $order): ?string
    {
        $pdfDocument = $order->documents
            ->first(fn (Document $document) => $document->observation === 'PDF_GENERATED_SUPPLIER_PURCHASE_ORDER'
                && $document->mime_type === 'application/pdf'
                && $document->file_path
                && Storage::disk('public')->exists($document->file_path));

        return $pdfDocument
            ? Storage::disk('public')->url($pdfDocument->file_path)
                .'?v='.$pdfDocument->updated_at?->timestamp
            : null;
    }

    private function applyCustomerOrderSearch($query, string $keyword): void
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

    public function generateCode(Request $request)
    {
        $supplierAccountId = $request->input('supplier_account_id');

        if (! $supplierAccountId) {
            return response()->json([
                'code' => '',
                'message' => 'Seleccione una cuenta bancaria para generar el numero de orden.',
            ]);
        }

        $account = SupplierAccount::query()
            ->with('bank')
            ->find($supplierAccountId);

        if (! $account) {
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

    public function companyBankAccounts(Request $request)
    {
        $validated = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'currency_id' => ['required', 'integer', 'exists:currencies,id'],
        ], [
            'company_id.required' => 'Seleccione la empresa de la orden.',
            'company_id.exists' => 'La empresa seleccionada no existe.',
            'currency_id.required' => 'Seleccione la moneda de pago.',
            'currency_id.exists' => 'La moneda de pago seleccionada no existe.',
        ]);

        $accounts = CompanyBankAccount::query()
            ->with([
                'company:id,business_name,trade_name',
                'bank:id,description,short_name',
                'currency:id,code,symbol',
            ])
            ->where('company_id', $validated['company_id'])
            ->where('currency_id', $validated['currency_id'])
            ->where('status', 'ACTIVE')
            ->orderBy('bank_id')
            ->orderBy('account_number')
            ->get()
            ->map(function (CompanyBankAccount $account) {
                $company = $account->company?->trade_name ?: $account->company?->business_name;
                $bank = $account->bank?->short_name ?: $account->bank?->description;
                $currency = $account->currency?->code ?: '-';
                $symbol = $account->currency?->symbol ?: $currency;

                return [
                    'id' => $account->id,
                    'company_id' => $account->company_id,
                    'currency_id' => $account->currency_id,
                    'company_name' => $company ?: 'Empresa sin nombre',
                    'bank_name' => $bank ?: 'Banco sin nombre',
                    'currency_code' => $currency,
                    'currency_symbol' => $symbol,
                    'account_number' => $account->account_number,
                    'balance' => (float) $account->current_balance,
                    'label' => collect([
                        $company ?: 'Empresa sin nombre',
                        $bank ?: 'Banco sin nombre',
                        $currency,
                        $account->account_number,
                        'Saldo: '.$symbol.' '.number_format((float) $account->current_balance, 2),
                    ])->filter()->join(' · '),
                ];
            })
            ->unique('id')
            ->values();

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
            'paymentCurrency',
            'customerPurchaseOrder.customer',
            'customerPurchaseOrder.currency',
            'customerPurchaseOrders.customer',
            'customerPurchaseOrders.currency',
            'quote',
            'marketStudy',
            'destinationUbigeo',
            'shippingAgency',
            'shippingAgencyBranch',
            'shippingAgencyContact',
            'creator',
            'updater',
            'documents.documentType',
            'items.article',
            'items.marketStudyItem',
            'items.quoteItem',
            'items.customerPurchaseOrderItem',
            'items.unit',
            'items.presentation',
            'items.brand',
            'advancePayments.currency',
            'advancePayments.purchaseCurrency',
            'advancePayments.supplierAccount.bank',
            'advancePayments.companyBankAccount.bank',
            'advancePayments.companyBankAccount.currency',
            'advancePayments.creator:id,name',
        ]);
        $this->appendEntryProgress($supplierPurchaseOrder);
        $supplierPurchaseOrder->setAttribute(
            'supplier_documents',
            $supplierPurchaseOrder->documents
                ->filter(fn (Document $document) => in_array(
                    $document->documentType?->code,
                    self::SUPPLIER_DOCUMENT_TYPE_CODES,
                    true
                ))
                ->map(function (Document $document) use ($supplierPurchaseOrder) {
                    $document->setAttribute('view_url', route(
                        'admin.supplier-purchase-orders.documents.view',
                        [$supplierPurchaseOrder, $document]
                    ));
                    $document->setAttribute('delete_url', route(
                        'admin.supplier-purchase-orders.documents.destroy',
                        [$supplierPurchaseOrder, $document]
                    ));

                    return $document;
                })
                ->values()
        );
        $financialService = app(SupplierPurchaseOrderFinancialService::class);
        $supplierPurchaseOrder->advancePayments->each(function (SupplierPurchaseOrderAdvancePayment $payment) use ($supplierPurchaseOrder, $financialService) {
            $payment->setAttribute('proof_url', $payment->proof_path
                ? route('admin.supplier-purchase-orders.advance-payments.proof', [$supplierPurchaseOrder, $payment])
                : null);
            $payment->setAttribute(
                'effective_applied_amount',
                $financialService->effectiveAppliedAmount($payment, $supplierPurchaseOrder)
            );
        });

        return response()->json([
            'status' => 'success',
            'data' => $supplierPurchaseOrder,
        ]);
    }

    public function edit(SupplierPurchaseOrder $supplierPurchaseOrder)
    {
        return $this->show($supplierPurchaseOrder);
    }

    public function viewDocument(SupplierPurchaseOrder $supplierPurchaseOrder, Document $document)
    {
        $this->ensureSupplierOrderDocument($supplierPurchaseOrder, $document);
        abort_unless(Storage::disk('public')->exists($document->file_path), 404);

        $extension = strtolower((string) ($document->extension
            ?: pathinfo((string) $document->original_name, PATHINFO_EXTENSION)));
        $extension = preg_replace('/[^a-z0-9]/', '', $extension);
        $supplierName = $supplierPurchaseOrder->supplier?->business_name
            ?? $supplierPurchaseOrder->supplier?->short_name
            ?? 'DOCUMENTO_PROVEEDOR';
        $responseName = $this->sanitizeSupplierDocumentFileName($supplierName)
            .($extension !== '' ? '.'.$extension : '');

        return Storage::disk('public')->response($document->file_path, $responseName, [
            'Content-Type' => $document->mime_type ?: 'application/octet-stream',
        ]);
    }

    public function viewAdvancePaymentProof(
        SupplierPurchaseOrder $supplierPurchaseOrder,
        SupplierPurchaseOrderAdvancePayment $advancePayment
    ) {
        abort_unless(
            (int) $advancePayment->supplier_purchase_order_id === (int) $supplierPurchaseOrder->id
                && $advancePayment->proof_path,
            404
        );
        abort_unless(Storage::disk('public')->exists($advancePayment->proof_path), 404);
        $fileName = str_replace(
            ["\r", "\n", '"'],
            '',
            basename($advancePayment->proof_original_name ?: $advancePayment->proof_path)
        );

        return Storage::disk('public')->response(
            $advancePayment->proof_path,
            $fileName,
            ['Content-Type' => $advancePayment->proof_mime_type ?: 'application/octet-stream']
        );
    }

    public function destroyDocument(SupplierPurchaseOrder $supplierPurchaseOrder, Document $document)
    {
        $this->ensureSupplierOrderDocument($supplierPurchaseOrder, $document);

        if ($document->file_path && Storage::disk('public')->exists($document->file_path)) {
            Storage::disk('public')->delete($document->file_path);
        }

        $document->update(['deleted_by' => Auth::id()]);
        $document->delete();

        return response()->json(['message' => 'Documento eliminado correctamente.']);
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
            Log::error('Error deleting supplier purchase order: '.$e->getMessage());

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
        $request->merge([
            'delivery_type' => SupplierPurchaseOrder::normalizeDeliveryType(
                $request->input('delivery_type')
            ),
        ]);

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
            'payment_currency_id' => ['required', 'exists:currencies,id'],
            'apply_exchange_rate' => ['nullable', 'boolean'],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            'apply_advance' => ['nullable', 'boolean'],
            'advance_type' => ['nullable', Rule::in(['fixed_amount', 'percentage'])],
            'advance_percentage' => ['nullable', 'numeric', 'gt:0', 'lte:100'],
            'advance_amount' => ['nullable', 'numeric', 'gt:0'],
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
            'payment_condition' => ['required', Rule::in($this->paymentConditionOptions())],
            'credit_days' => [
                Rule::requiredIf(fn () => $request->input('payment_condition') === 'credito'),
                'nullable',
                'integer',
                'min:1',
            ],
            'payment_due_date' => ['nullable', 'date'],
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
            'items.*.id' => [
                'nullable',
                'integer',
                Rule::exists('supplier_purchase_order_items', 'id')
                    ->where('supplier_purchase_order_id', $order?->id ?? 0),
            ],
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
            'supplier_documents' => ['nullable', 'array'],
            'supplier_documents.*.type' => [
                'nullable',
                'required_with:supplier_documents.*.file',
                Rule::in(self::SUPPLIER_DOCUMENT_TYPES),
            ],
            'supplier_documents.*.observation' => ['nullable', 'string', 'max:500'],
            'supplier_documents.*.file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'advance_payments' => ['nullable', 'array'],
            'advance_payments.*.purchase_currency_id' => ['nullable', 'exists:currencies,id'],
            'advance_payments.*.payment_currency_id' => [
                'nullable',
                'required_with:advance_payments.*.applied_amount',
                'exists:currencies,id',
            ],
            'advance_payments.*.applied_amount' => ['nullable', 'numeric', 'gt:0'],
            'advance_payments.*.amount' => ['nullable', 'numeric', 'gt:0'],
            'advance_payments.*.exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            'advance_payments.*.company_bank_account_id' => [
                'nullable',
                'required_with:advance_payments.*.applied_amount',
                'exists:company_bank_accounts,id',
            ],
            'advance_payments.*.payment_date' => ['nullable', 'required_with:advance_payments.*.applied_amount', 'date'],
            'advance_payments.*.payment_method' => [
                'nullable',
                'required_with:advance_payments.*.applied_amount',
                Rule::in($this->paymentMethodOptions()),
            ],
            'advance_payments.*.operation_number' => ['nullable', 'string', 'max:100'],
            'advance_payments.*.observation' => ['nullable', 'string', 'max:1000'],
            'advance_payments.*.proof' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ], [
            'company_id.required' => 'La empresa es obligatoria.',
            'supplier_id.required' => 'El proveedor es obligatorio.',
            'supplier_account_id.required' => 'Debe seleccionar o registrar una cuenta bancaria del proveedor.',
            'supplier_account_id.exists' => 'La cuenta bancaria debe pertenecer al proveedor y estar activa.',
            'currency_id.required' => 'La moneda es obligatoria.',
            'payment_currency_id.required' => 'La moneda de pago es obligatoria.',
            'payment_condition.required' => 'La condición de pago es obligatoria.',
            'payment_condition.in' => 'Seleccione Contado o Crédito como condición de pago.',
            'credit_days.required' => 'Ingrese los días de crédito.',
            'credit_days.integer' => 'Los días de crédito deben ser un número entero.',
            'credit_days.min' => 'Los días de crédito deben ser mayores a 0.',
            'exchange_rate.gt' => 'El tipo de cambio debe ser mayor a cero.',
            'advance_type.in' => 'Seleccione Monto fijo o Porcentaje como tipo de anticipo.',
            'advance_percentage.gt' => 'El porcentaje del anticipo debe ser mayor a cero.',
            'advance_percentage.lte' => 'El porcentaje del anticipo no puede superar el 100%.',
            'advance_amount.gt' => 'El monto del anticipo debe ser mayor a cero.',
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
            'supplier_documents.*.file.mimes' => 'Solo se permiten archivos PDF, JPG, JPEG o PNG.',
            'supplier_documents.*.file.max' => 'El archivo no debe superar los 10 MB.',
            'advance_payments.*.payment_currency_id.required_with' => 'Seleccione la moneda de este pago.',
            'advance_payments.*.payment_currency_id.exists' => 'La moneda seleccionada para el pago no existe.',
            'advance_payments.*.applied_amount.gt' => 'El monto aplicado a la compra debe ser mayor a cero.',
            'advance_payments.*.amount.gt' => 'El monto pagado debe ser mayor a cero.',
            'advance_payments.*.exchange_rate.gt' => 'El tipo de cambio del pago debe ser mayor a cero.',
            'advance_payments.*.company_bank_account_id.required_with' => 'Seleccione la cuenta bancaria de la empresa desde la que se pagó el anticipo.',
            'advance_payments.*.company_bank_account_id.exists' => 'La cuenta bancaria de origen seleccionada no existe.',
            'advance_payments.*.payment_date.required_with' => 'Ingrese la fecha del pago del anticipo.',
            'advance_payments.*.payment_method.required_with' => 'Seleccione el medio de pago del anticipo.',
            'advance_payments.*.proof.mimes' => 'La constancia del anticipo debe ser PDF, JPG, JPEG, PNG o WEBP.',
            'advance_payments.*.proof.max' => 'La constancia del anticipo no debe superar los 10 MB.',
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
        $uploadedDocumentPaths = [];

        try {
            return DB::transaction(function () use (
                $validated,
                $order,
                &$generatedPdfPath,
                &$generatedPdfUrl,
                &$generatedDocumentId,
                &$pdfError,
                &$uploadedDocumentPaths
            ) {
                $customerOrderIds = collect($validated['customer_purchase_order_ids'])
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values()
                    ->all();
                $previousCustomerOrderIds = $order
                    ? $this->customerPurchaseOrderIdsForSupplierOrder($order)
                    : collect();
                $this->validateCustomerOrdersAvailableForSupplierPurchase(
                    $customerOrderIds,
                    $previousCustomerOrderIds
                );
                $affectIgv = (bool) ($validated['affect_igv'] ?? true);
                $this->validateCustomerOrderItemUnitPrices($validated['items']);
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
                $purchaseCurrency = Currency::query()->findOrFail($validated['currency_id']);
                $paymentCurrency = Currency::query()->findOrFail($validated['payment_currency_id']);
                $existingAdvancePayments = $order
                    ? $order->advancePayments()->with(['currency', 'purchaseCurrency'])->get()
                    : collect();
                $newAdvancePayments = collect($validated['advance_payments'] ?? [])
                    ->filter(fn ($payment) => (float) ($payment['applied_amount'] ?? 0) > 0)
                    ->values();
                $financialService = app(SupplierPurchaseOrderFinancialService::class);
                if ($order) {
                    $order->setRelation('currency', $purchaseCurrency);
                    $order->setRelation('paymentCurrency', $paymentCurrency);
                }

                if ($newAdvancePayments->isNotEmpty() && ! (bool) ($validated['apply_advance'] ?? false)) {
                    throw ValidationException::withMessages([
                        'apply_advance' => 'Active el anticipo antes de registrar un pago.',
                    ]);
                }

                if ($newAdvancePayments->isNotEmpty()) {
                    abort_unless(Auth::user()?->can('admin.banks.movements.create'), 403);

                    $newAdvancePayments = $this->prepareAdvancePayments(
                        $newAdvancePayments,
                        $purchaseCurrency,
                        (int) $validated['company_id'],
                        $financialService
                    );
                }

                if ($existingAdvancePayments->isNotEmpty()
                    && (int) $order->company_id !== (int) $validated['company_id']) {
                    throw ValidationException::withMessages([
                        'company_id' => 'No puede cambiar la empresa porque la orden ya tiene anticipos registrados.',
                    ]);
                }
                if ($existingAdvancePayments->isNotEmpty()
                    && (int) $order->payment_currency_id !== (int) $paymentCurrency->id) {
                    throw ValidationException::withMessages([
                        'payment_currency_id' => 'No puede cambiar la moneda de pago porque la orden ya tiene anticipos registrados.',
                    ]);
                }
                if ($existingAdvancePayments->isNotEmpty()
                    && (int) $order->currency_id !== (int) $purchaseCurrency->id) {
                    throw ValidationException::withMessages([
                        'currency_id' => 'No puede cambiar la moneda de compra porque la orden ya tiene anticipos registrados.',
                    ]);
                }
                if ($existingAdvancePayments->isNotEmpty() && ! (bool) ($validated['apply_advance'] ?? false)) {
                    throw ValidationException::withMessages([
                        'apply_advance' => 'No puede desactivar el anticipo porque existen pagos registrados.',
                    ]);
                }

                $exchangeRate = isset($validated['exchange_rate']) ? (float) $validated['exchange_rate'] : null;
                $existingPaidApplied = round((float) $existingAdvancePayments->sum(
                    fn (SupplierPurchaseOrderAdvancePayment $payment) =>
                        $financialService->effectiveAppliedAmount($payment, $order) ?? 0
                ), 4);
                $newPaidAmount = round((float) $newAdvancePayments->sum('applied_amount'), 4);
                try {
                    $newPaidAmountPen = round((float) $newAdvancePayments->sum('amount_pen'), 4);
                    $paidAmount = round($existingPaidApplied + $newPaidAmount, 4);
                    $paidAmountPen = round((float) $existingAdvancePayments->sum('amount_pen') + $newPaidAmountPen, 4);
                    if ($paidAmount > $totals['grand_total'] + 0.0001) {
                        throw new \InvalidArgumentException('El monto aplicado no puede superar el saldo pendiente de la compra.');
                    }
                    $financialData = $financialService->calculate(
                        $totals['grand_total'],
                        $purchaseCurrency->code,
                        $paymentCurrency->code,
                        (bool) ($validated['apply_exchange_rate'] ?? false),
                        $exchangeRate,
                        (bool) ($validated['apply_advance'] ?? false),
                        $validated['advance_type'] ?? null,
                        isset($validated['advance_percentage']) ? (float) $validated['advance_percentage'] : null,
                        isset($validated['advance_amount']) ? (float) $validated['advance_amount'] : null,
                        $paidAmount,
                        $paidAmountPen,
                        $validated['payment_condition'] ?? null
                    );
                } catch (\InvalidArgumentException $exception) {
                    throw ValidationException::withMessages([
                        'financial_terms' => $exception->getMessage(),
                    ]);
                }
                $isAgencyDelivery = $this->deliveryRequiresShippingAgency($validated['delivery_type'] ?? null);
                $isCredit = ($validated['payment_condition'] ?? null) === 'credito';
                $creditDays = $isCredit ? (int) $validated['credit_days'] : null;
                $paymentBaseDate = $order?->created_at instanceof Carbon
                    ? $order->created_at->copy()
                    : now();
                $paymentDueDate = $isCredit
                    ? $paymentBaseDate->startOfDay()->addDays($creditDays)->toDateString()
                    : null;
                $supplierAccount = SupplierAccount::query()
                    ->with('bank')
                    ->findOrFail($validated['supplier_account_id']);
                $company = Company::query()->findOrFail($validated['company_id']);
                $isPraga = $this->isPragaCompany($company);
                $authorizedByName = $isPraga
                    ? 'ROSA L. VINCES VALDERRAMA'
                    : ($validated['authorized_by_name'] ?? 'IVAN CUBAS BINCES');
                $authorizedByPosition = $isPraga
                    ? 'GERENTE GENERAL'
                    : ($validated['authorized_by_position'] ?? 'GERENTE GENERAL');

                $orderData = [
                    'company_id' => $validated['company_id'],
                    'supplier_id' => $validated['supplier_id'],
                    'supplier_account_id' => $validated['supplier_account_id'] ?? null,
                    'currency_id' => $validated['currency_id'],
                    'payment_currency_id' => $validated['payment_currency_id'],
                    'customer_purchase_order_id' => $customerOrderIds[0] ?? null,
                    'quote_id' => $validated['quote_id'] ?? null,
                    'market_study_id' => $validated['market_study_id'] ?? null,
                    'order_type' => $validated['order_type'] ?? 'articles',
                    'payment_condition' => $validated['payment_condition'] ?? null,
                    'credit_days' => $creditDays,
                    'payment_due_date' => $paymentDueDate,
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
                    'authorized_by_name' => $this->upperOrNull($authorizedByName),
                    'authorized_by_position' => $this->upperOrNull($authorizedByPosition),
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
                    ...$financialData,
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

                if ($wasRecentlyCreated) {
                    $order->trackings()->firstOrCreate(
                        ['status' => 'registered'],
                        [
                            'title' => 'Orden registrada',
                            'event_date' => now(),
                            'created_by' => Auth::id(),
                            'updated_by' => Auth::id(),
                        ]
                    );
                }

                $retainedItemIds = [];
                foreach ($preparedItems as $item) {
                    $itemId = $item['_item_id'] ?? null;
                    unset($item['_item_id']);

                    $orderItem = $itemId
                        ? $order->items()->whereKey($itemId)->firstOrFail()
                        : $order->items()->make();
                    $orderItem->fill($item)->save();
                    $retainedItemIds[] = $orderItem->id;
                }

                if (! $wasRecentlyCreated) {
                    $order->items()
                        ->whereNotIn('id', $retainedItemIds)
                        ->update(['status' => 'deleted']);
                }

                $uploadedDocumentPaths = array_merge(
                    $uploadedDocumentPaths,
                    $this->storeAdvancePayments(
                        $order,
                        $newAdvancePayments
                    )
                );

                $order->customerPurchaseOrders()->sync($customerOrderIds);
                $uploadedDocumentPaths = array_merge(
                    $uploadedDocumentPaths,
                    $this->storeSupplierOrderDocuments($order, $validated['supplier_documents'] ?? [])
                );
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
                        'paymentCurrency',
                        'advancePayments.currency',
                        'advancePayments.purchaseCurrency',
                        'advancePayments.supplierAccount.bank',
                        'advancePayments.companyBankAccount.bank',
                        'advancePayments.companyBankAccount.currency',
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

                    Log::error('Error generating supplier purchase order PDF: '.$pdfException->getMessage());
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

            foreach ($uploadedDocumentPaths as $uploadedDocumentPath) {
                if (Storage::disk('public')->exists($uploadedDocumentPath)) {
                    Storage::disk('public')->delete($uploadedDocumentPath);
                }
            }

            Log::error('Error saving supplier purchase order: '.$e->getMessage());

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
                $unitPrice = round((float) $item['unit_price'], 6);
                $totalWithIgv = $quantity * $unitPrice;
                $taxableBase = $affectIgv
                    ? $totalWithIgv / 1.18
                    : $totalWithIgv;
                $taxAmount = $affectIgv
                    ? $totalWithIgv - $taxableBase
                    : 0;

                return [
                    '_item_id' => $item['id'] ?? null,
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
                        6
                    ),
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    // Legacy fields remain synchronized for existing consumers.
                    'subtotal' => $taxableBase,
                    'tax_amount' => $taxAmount,
                    'line_total' => $totalWithIgv,
                    'total_with_igv' => $totalWithIgv,
                    'taxable_base' => $taxableBase,
                    'igv_percent' => $affectIgv ? 18.00 : 0.00,
                    'igv_amount' => $taxAmount,
                    'status' => $item['status'] ?? 'active',
                ];
            })
            ->all();
    }

    private function validateCustomerOrderItemUnitPrices(array $items): void
    {
        $customerItems = CustomerPurchaseOrderItem::query()
            ->with('purchaseOrder.currency:id,symbol,code')
            ->whereIn(
                'id',
                collect($items)
                    ->pluck('customer_purchase_order_item_id')
                    ->filter()
                    ->unique()
                    ->all()
            )
            ->get()
            ->keyBy('id');

        foreach ($items as $index => $item) {
            $customerItemId = $item['customer_purchase_order_item_id'] ?? null;

            if (! $customerItemId || ! $customerItems->has($customerItemId)) {
                continue;
            }

            $customerItem = $customerItems->get($customerItemId);
            $purchasePrice = (float) ($item['unit_price'] ?? 0);
            $maximumPrice = (float) $customerItem->unit_price;

            if ($purchasePrice <= $maximumPrice) {
                continue;
            }

            $articleName = $customerItem->billing_name_snapshot
                ?: $customerItem->article_code
                ?: 'seleccionado';
            $currency = $customerItem->purchaseOrder?->currency?->symbol
                ?: $customerItem->purchaseOrder?->currency?->code
                ?: 'S/';

            throw ValidationException::withMessages([
                "items.$index.unit_price" => sprintf(
                    'El precio de compra del artículo %s no puede ser mayor al precio de la Orden de Compra del Cliente. Precio cliente: %s %s.',
                    $articleName,
                    $currency,
                    rtrim(rtrim(number_format($maximumPrice, 6, '.', ''), '0'), '.')
                ),
            ]);
        }
    }

    private function calculateTotals(array $items): array
    {
        $subtotal = round((float) collect($items)->sum('subtotal'), 2);
        $igv = round((float) collect($items)->sum('tax_amount'), 2);
        $grandTotal = round((float) collect($items)->sum('total_with_igv'), 2);

        return [
            'subtotal' => $subtotal,
            'igv' => $igv,
            'grand_total' => $grandTotal,
        ];
    }

    private function customerPurchaseOrderItemsResponse(
        $orders,
        ?int $supplierId = null,
        $supplierPurchaseOrderId = null
    ) {
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
                    ($order->customer?->first_name ?? '').' '.
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

                if (! $supplierId) {
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
                // Precio de este item concreto en la OC Cliente. Es solo para UX;
                // la validación real consulta nuevamente la BD usando el ID del item.
                $payload['customer_unit_price'] = (float) $item->unit_price;

                return array_merge(
                    $payload,
                    [
                        'customer_purchase_order_id' => $order->id,
                        'customer_purchase_order_code' => $order->purchase_order_number ?: $order->code,
                        'customer_order_code' => $order->purchase_order_number ?: $order->code,
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

    private function validateCustomerOrdersAvailableForSupplierPurchase(
        array $customerOrderIds,
        $previousCustomerOrderIds
    ): void {
        $previousIds = collect($previousCustomerOrderIds)
            ->map(fn ($id) => (int) $id)
            ->all();

        $hasUnavailableOrder = CustomerPurchaseOrder::query()
            ->whereIn('id', array_diff($customerOrderIds, $previousIds))
            ->whereNotIn('id', CustomerPurchaseOrder::query()->availableForSupplierPurchase()->select('id'))
            ->lockForUpdate()
            ->exists();

        if ($hasUnavailableOrder) {
            throw ValidationException::withMessages([
                'customer_purchase_order_ids' => 'La orden de cliente ya no tiene artículos con cantidad pendiente de compra.',
            ]);
        }
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

        $duplicateItemId = collect($customerOrderItemIds)->map(fn ($id) => (int) $id)->duplicates()->first();
        if ($duplicateItemId) {
            throw ValidationException::withMessages([
                'items' => 'No se puede duplicar el mismo artículo de la orden del cliente en una orden a proveedor.',
            ]);
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
                    "items.$index.quantity" => 'La cantidad a comprar no puede superar la cantidad pendiente del cliente.',
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

                if (! $customerItem) {
                    throw ValidationException::withMessages([
                        "items.$index.customer_purchase_order_item_id" => 'El item de orden cliente no existe.',
                    ]);
                }

                if (! in_array((int) $customerItem->customer_purchase_order_id, $customerOrderIds, true)) {
                    throw ValidationException::withMessages([
                        "items.$index.customer_purchase_order_item_id" => 'El item no pertenece a las ordenes cliente seleccionadas.',
                    ]);
                }
            }

            $marketStudyItemId = $item['market_study_item_id']
                ?? $customerItem?->market_study_item_id
                ?? $customerItem?->quoteItem?->market_study_item_id;

            if (! $customerItemId && ! $marketStudyItemId) {
                continue;
            }

            if (! $marketStudyItemId) {
                continue;
            }

            if (! $awardMap->has((int) $marketStudyItemId)) {
                throw ValidationException::withMessages([
                    "items.$index.article_id" => 'El item no esta adjudicado al proveedor seleccionado.',
                ]);
            }

            $award = $awardMap->get((int) $marketStudyItemId);
            $winnerPrice = round((float) ($award->unit_price ?? 0), 6);

            $items[$index]['market_study_item_id'] = (int) $marketStudyItemId;
            $items[$index]['unit_price'] = round(
                (float) ($item['unit_price'] ?? $winnerPrice),
                6
            );
            $items[$index]['reference_purchase_price'] = $winnerPrice;

            if (! empty($award->article_id)) {
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
    ): array {
        $article = $item->article;
        $quantity = round((float) ($item->quantity ?? 1), 2);
        $unitPrice = round(
            (float) ($award->unit_price ?? $item->unit_price ?? $item->cost_price ?? 0),
            6
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
            $code = str_pad((string) $lastSequence, 5, '0', STR_PAD_LEFT).'-'.$year.'-'.$bankCode;
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

        if (! $rawCode) {
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
        $fileName = 'orden_compra_proveedor_'.$this->sanitizeFileName($order->code).'.pdf';
        $storedPath = 'supplier-purchase-orders/'.$fileName;

        $pdf = Pdf::loadView('admin.supplier-purchase-orders.pdf', [
            'order' => $order,
            'logoUrl' => $this->supplierOrderLogoUrl($order),
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
            'url' => Storage::disk('public')->url($storedPath)
                .'?v='.now()->format('YmdHisv'),
            'document' => $document,
        ];
    }

    private function storeAdvancePayments(
        SupplierPurchaseOrder $order,
        $payments
    ): array {
        $storedPaths = [];

        foreach ($payments as $paymentData) {
            $file = $paymentData['proof'] ?? null;
            $storedPath = null;
            if ($file && $file->isValid()) {
                $storedPath = $file->store(
                    "supplier-purchase-orders/{$order->id}/advance-payments",
                    'public'
                );
                $storedPaths[] = $storedPath;
            }

            $advancePayment = $order->advancePayments()->create([
                'supplier_account_id' => $order->supplier_account_id,
                'company_bank_account_id' => $paymentData['company_bank_account_id'],
                'purchase_currency_id' => $paymentData['purchase_currency_id'],
                'currency_id' => $paymentData['payment_currency_id'],
                'payment_date' => $paymentData['payment_date'],
                'applied_amount' => $paymentData['applied_amount'],
                'amount' => $paymentData['amount'],
                'amount_pen' => $paymentData['amount_pen'],
                'exchange_rate' => $paymentData['exchange_rate'],
                'payment_method' => $paymentData['payment_method'],
                'operation_number' => $this->upperOrNull($paymentData['operation_number'] ?? null),
                'proof_path' => $storedPath,
                'proof_original_name' => $file?->getClientOriginalName(),
                'proof_mime_type' => $file?->getMimeType(),
                'proof_size' => $file?->getSize(),
                'observation' => $this->upperOrNull($paymentData['observation'] ?? null),
                'status' => 'ACTIVE',
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            app(BankMovementService::class)->createMovement([
                'company_bank_account_id' => $advancePayment->company_bank_account_id,
                'currency_id' => $advancePayment->currency_id,
                'movement_date' => $advancePayment->payment_date->toDateString(),
                'movement_type' => 'EGRESO',
                'amount' => $advancePayment->amount,
                'exchange_rate' => $advancePayment->exchange_rate,
                'amount_pen' => $advancePayment->amount_pen,
                'direction' => 'OUT',
                'concept' => 'Anticipo a proveedor',
                'description' => $advancePayment->observation,
                'operation_number' => $advancePayment->operation_number,
                'file_path' => $advancePayment->proof_path,
                'file_original_name' => $advancePayment->proof_original_name,
                'file_mime_type' => $advancePayment->proof_mime_type,
                'file_size' => $advancePayment->proof_size,
                'source_type' => 'SUPPLIER_ADVANCE',
                'source_id' => $advancePayment->id,
                'source_code' => $order->code,
                'source_description' => 'Anticipo de la orden de compra a proveedor',
                'idempotency_key' => "supplier-advance:{$advancePayment->id}",
            ], Auth::id());
        }

        return $storedPaths;
    }

    private function prepareAdvancePayments(
        $payments,
        Currency $purchaseCurrency,
        int $companyId,
        SupplierPurchaseOrderFinancialService $financialService
    ) {
        $paymentCurrencies = Currency::query()
            ->whereIn('id', collect($payments)->pluck('payment_currency_id')->filter()->unique())
            ->get()
            ->keyBy('id');

        $prepared = collect($payments)->values()->map(function (array $payment, int $index) use (
            $purchaseCurrency,
            $paymentCurrencies,
            $financialService
        ) {
            $currencyField = "advance_payments.{$index}.payment_currency_id";
            $rateField = "advance_payments.{$index}.exchange_rate";
            $postedPurchaseCurrencyId = (int) ($payment['purchase_currency_id'] ?? $purchaseCurrency->id);
            if ($postedPurchaseCurrencyId !== (int) $purchaseCurrency->id) {
                throw ValidationException::withMessages([
                    "advance_payments.{$index}.purchase_currency_id" => 'La moneda de compra del pago no coincide con la moneda de la orden.',
                ]);
            }

            $paymentCurrency = $paymentCurrencies->get((int) ($payment['payment_currency_id'] ?? 0));
            if (! $paymentCurrency) {
                throw ValidationException::withMessages([
                    $currencyField => 'Seleccione una moneda de pago válida.',
                ]);
            }

            $purchaseCode = strtoupper((string) $purchaseCurrency->code);
            $paymentCode = strtoupper((string) $paymentCurrency->code);
            if ($purchaseCode !== $paymentCode && $purchaseCode !== 'PEN' && $paymentCode !== 'PEN') {
                throw ValidationException::withMessages([
                    $currencyField => 'Una de las monedas del pago debe ser PEN.',
                ]);
            }

            $rate = $purchaseCode === $paymentCode
                ? 1.0
                : (float) ($payment['exchange_rate'] ?? 0);
            if ($purchaseCode !== $paymentCode && $rate <= 0) {
                throw ValidationException::withMessages([
                    $rateField => 'Ingrese el tipo de cambio de este pago, mayor a cero.',
                ]);
            }

            $appliedAmount = round((float) ($payment['applied_amount'] ?? 0), 4);
            $paidAmount = $financialService->convertAppliedToPaid(
                $appliedAmount,
                $purchaseCode,
                $paymentCode,
                $rate
            );

            return [
                ...$payment,
                'purchase_currency_id' => (int) $purchaseCurrency->id,
                'payment_currency_id' => (int) $paymentCurrency->id,
                'applied_amount' => $appliedAmount,
                'amount' => $paidAmount,
                'exchange_rate' => $rate,
                'amount_pen' => $financialService->amountInPen($paidAmount, $paymentCode, $rate),
            ];
        });

        $this->validateAdvancePaymentBankAccounts($prepared, $companyId);

        return $prepared;
    }

    private function validateAdvancePaymentBankAccounts(
        $payments,
        int $companyId
    ): void {
        $accounts = CompanyBankAccount::query()
            ->whereIn('id', collect($payments)->pluck('company_bank_account_id')->filter()->unique())
            ->get()
            ->keyBy('id');

        foreach (collect($payments)->values() as $index => $payment) {
            $field = "advance_payments.{$index}.company_bank_account_id";
            $account = $accounts->get((int) ($payment['company_bank_account_id'] ?? 0));

            if (! $account) {
                throw ValidationException::withMessages([
                    $field => 'La cuenta bancaria de origen seleccionada no existe.',
                ]);
            }

            if ($account->status !== 'ACTIVE') {
                throw ValidationException::withMessages([
                    $field => 'La cuenta bancaria seleccionada no se encuentra activa.',
                ]);
            }

            if ((int) $account->company_id !== $companyId) {
                throw ValidationException::withMessages([
                    $field => 'La cuenta bancaria seleccionada no pertenece a la empresa de la orden o no corresponde a la moneda seleccionada.',
                ]);
            }

            if ((int) $account->currency_id !== (int) ($payment['payment_currency_id'] ?? 0)) {
                throw ValidationException::withMessages([
                    $field => 'La cuenta bancaria seleccionada no pertenece a la empresa de la orden o no corresponde a la moneda seleccionada.',
                ]);
            }
        }
    }

    private function storeSupplierOrderDocuments(SupplierPurchaseOrder $order, array $documents): array
    {
        $storedPaths = [];

        foreach ($documents as $documentData) {
            $file = $documentData['file'] ?? null;

            if (! $file || ! $file->isValid()) {
                continue;
            }

            $type = $this->resolveSupplierOrderDocumentType($documentData['type'] ?? 'other');
            $storedPath = $file->store(
                'supplier-purchase-orders/documents/'.$order->id,
                'public'
            );
            $storedPaths[] = $storedPath;

            $order->documents()->create([
                'document_type_id' => $type->id,
                'original_name' => $file->getClientOriginalName(),
                'stored_name' => basename($storedPath),
                'file_path' => $storedPath,
                'mime_type' => $file->getMimeType(),
                'extension' => strtolower($file->getClientOriginalExtension()),
                'file_size' => $file->getSize(),
                'observation' => $this->upperOrNull($documentData['observation'] ?? null),
                'status' => 'ACTIVE',
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);
        }

        return $storedPaths;
    }

    private function resolveSupplierOrderDocumentType(string $type): DocumentType
    {
        $types = [
            'supplier_quote' => ['code' => 'SPO_QUOTE', 'description' => 'COTIZACION DEL PROVEEDOR'],
            'payment_support' => ['code' => 'SPO_PAYMENT_SUPPORT', 'description' => 'SUSTENTO DE PAGO'],
            'other' => ['code' => 'SPO_OTHER', 'description' => 'OTRO DOCUMENTO DEL PROVEEDOR'],
        ];
        $definition = $types[$type] ?? $types['other'];

        return DocumentType::query()->firstOrCreate(
            ['code' => $definition['code']],
            [
                'description' => $definition['description'],
                'observation' => 'Sustento interno de orden de compra a proveedor',
                'status' => 'ACTIVE',
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]
        );
    }

    private function ensureSupplierOrderDocument(
        SupplierPurchaseOrder $supplierPurchaseOrder,
        Document $document
    ): void {
        abort_unless(
            $document->documentable_type === SupplierPurchaseOrder::class
                && (int) $document->documentable_id === (int) $supplierPurchaseOrder->id
                && in_array(
                    $document->documentType?->code,
                    self::SUPPLIER_DOCUMENT_TYPE_CODES,
                    true
                ),
            404
        );
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

    private function sanitizeSupplierDocumentFileName(string $value): string
    {
        $normalized = mb_strtoupper(Str::ascii(trim($value)));
        $normalized = preg_replace('/[^A-Z0-9\s_-]/', '', $normalized);
        $normalized = preg_replace('/\s+/', '_', trim((string) $normalized));

        return $normalized !== '' ? $normalized : 'DOCUMENTO_PROVEEDOR';
    }

    private function supplierOrderLogoUrl(SupplierPurchaseOrder $order): ?string
    {
        $defaultLogo = 'vendor/adminlte/dist/img/logo_img.png';
        $logo = $this->isPragaCompany($order->company)
            ? 'vendor/adminlte/dist/img/logopraga.png'
            : $defaultLogo;

        if (! file_exists(public_path($logo))) {
            $logo = $defaultLogo;
        }

        return file_exists(public_path($logo))
            ? url($logo)
            : null;
    }

    private function isPragaCompany(?Company $company): bool
    {
        if (! $company) {
            return false;
        }

        $companyName = mb_strtoupper(Str::ascii(trim(
            ($company->business_name ?? '').' '.($company->trade_name ?? '')
        )));

        return (string) $company->ruc === '20612701904'
            || str_contains($companyName, 'PRAGA');
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
            'credito',
        ];
    }

    private function deliveryTypeOptions(): array
    {
        return SupplierPurchaseOrder::DELIVERY_TYPES;
    }

    private function deliveryRequiresShippingAgency(?string $deliveryType): bool
    {
        return SupplierPurchaseOrder::normalizeDeliveryType($deliveryType)
            === SupplierPurchaseOrder::DELIVERY_TYPE_AGENCY;
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
        return app(CustomerPurchaseOrderStatusService::class)
            ->customerOrderIdsForSupplierOrder((int) $order->id);
    }

    private function refreshCustomerPurchaseOrderStatuses($customerPurchaseOrderIds): void
    {
        app(CustomerPurchaseOrderStatusService::class)
            ->syncMany($customerPurchaseOrderIds);
    }
}
