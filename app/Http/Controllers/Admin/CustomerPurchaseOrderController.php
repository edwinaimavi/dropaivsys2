<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerBranch;
use App\Models\CustomerPurchaseOrder;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Presentation;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Subcategory;
use App\Models\Unit;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class CustomerPurchaseOrderController extends Controller
{
    private const STATUS_REGISTERED = 'registered';
    private const STATUS_IN_PURCHASE = 'in_purchase';
    private const STATUS_PARTIAL_ENTERED = 'partial_entered';
    private const STATUS_ENTERED = 'entered';
    private const STATUS_CANCELLED = 'cancelled';
    private const STATUS_DELIVERED = 'delivered';
    private const STATUS_INVOICED = 'invoiced';

    public function __construct()
    {
        $this->middleware('can:admin.customer-purchase-orders.index')->only(['index', 'list', 'generateCode', 'customerBranches', 'searchCustomers']);
        $this->middleware('can:admin.customer-purchase-orders.load-items')->only(['quoteItems']);
        $this->middleware('can:admin.customer-purchase-orders.store')->only(['store', 'quickStoreCustomer']);
        $this->middleware('can:admin.customer-purchase-orders.update')->only(['update']);
        $this->middleware('can:admin.customer-purchase-orders.destroy')->only(['destroy']);
        $this->middleware('can:admin.customer-purchase-orders.show')->only(['show']);
    }

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
            ->whereIn('status', [
                Quote::STATUS_SENT,
                Quote::STATUS_APPROVED,
            ])
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

        $categories = Category::query()
            ->where('status', 'ACTIVE')
            ->orderBy('description')
            ->get();

        $subcategories = Subcategory::query()
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

        $documentTypes = DocumentType::query()
            ->where('status', 'ACTIVE')
            ->orderBy('description')
            ->get(['id', 'description']);

        return view('admin.customer-purchase-orders.index', compact(
            'companies',
            'customers',
            'quotes',
            'currencies',
            'units',
            'presentations',
            'brands',
            'categories',
            'subcategories',
            'articles',
            'documentTypes'
        ));
    }

    public function list()
    {
        $orders = CustomerPurchaseOrder::query()
            ->with([
                'company:id,business_name,trade_name',
                'customer:id,business_name,full_name,first_name,last_name',
                'customerBranch:id,branch_name',
                'currency:id,code,symbol,description',
            ])
            ->orderByDesc('id');

        return DataTables::of($orders)
            ->addIndexColumn()
            ->addColumn('company', function (CustomerPurchaseOrder $order) {
                return $order->company?->trade_name
                    ?? $order->company?->business_name
                    ?? '-';
            })
            ->addColumn('customer', function (CustomerPurchaseOrder $order) {
                $customer = $order->customer?->business_name
                    ?? $order->customer?->full_name
                    ?? trim(($order->customer?->first_name ?? '') . ' ' . ($order->customer?->last_name ?? ''))
                    ?: '-';

                $branch = $order->customerBranch?->branch_name;

                if (! $branch) {
                    return sprintf(
                        '<div class="customer-cell"><span class="customer-name-main">%s</span></div>',
                        e($customer)
                    );
                }

                return sprintf(
                    '<div class="customer-cell">
                        <span class="customer-name-main">%s</span>
                        <span class="customer-branch-badge"><i class="fas fa-map-marker-alt" aria-hidden="true"></i>%s</span>
                    </div>',
                    e($customer),
                    e($branch)
                );
            })
            ->addColumn('customer_text', function (CustomerPurchaseOrder $order) {
                $customer = $order->customer?->business_name
                    ?? $order->customer?->full_name
                    ?? trim(($order->customer?->first_name ?? '') . ' ' . ($order->customer?->last_name ?? ''))
                    ?: '-';
                $branch = $order->customerBranch?->branch_name;

                return $branch ? $customer . ' | ' . $branch : $customer;
            })
            ->filterColumn('customer', function ($query, string $keyword) {
                $query->where(function ($customerQuery) use ($keyword) {
                    $customerQuery
                        ->whereHas('customer', function ($relation) use ($keyword) {
                            $relation->where(function ($nameQuery) use ($keyword) {
                                $nameQuery
                                    ->where('business_name', 'like', "%{$keyword}%")
                                    ->orWhere('full_name', 'like', "%{$keyword}%")
                                    ->orWhere('first_name', 'like', "%{$keyword}%")
                                    ->orWhere('last_name', 'like', "%{$keyword}%");
                            });
                        })
                        ->orWhereHas('customerBranch', function ($relation) use ($keyword) {
                            $relation->where('branch_name', 'like', "%{$keyword}%");
                        });
                });
            })
            ->addColumn('currency', function (CustomerPurchaseOrder $order) {
                return $order->currency?->code
                    ?? $order->currency?->description
                    ?? '-';
            })
            ->editColumn('grand_total', function (CustomerPurchaseOrder $order) {
                $symbol = $order->currency?->symbol ?? '';

                return trim($symbol . ' ' . $this->formatDecimal($order->grand_total));
            })
            ->addColumn('delivery_period', fn (CustomerPurchaseOrder $order) => $this->deliveryPeriodHtml($order))
            ->editColumn('status', function (CustomerPurchaseOrder $order) {
                $statuses = [
                    'approved' => [
                        'label' => 'Registrada',
                        'class' => 'badge-secondary text-white',
                        'icon' => 'fas fa-clipboard-check',
                    ],
                    self::STATUS_REGISTERED => [
                        'label' => 'Registrada',
                        'class' => 'badge-secondary text-white',
                        'icon' => 'fas fa-clipboard-check',
                    ],
                    self::STATUS_IN_PURCHASE => [
                        'label' => 'En compra',
                        'class' => 'badge-warning text-dark',
                        'icon' => 'fas fa-shopping-cart',
                    ],
                    self::STATUS_PARTIAL_ENTERED => [
                        'label' => 'Ingreso parcial',
                        'class' => 'badge-info text-white',
                        'icon' => 'fas fa-dolly-flatbed',
                    ],
                    self::STATUS_ENTERED => [
                        'label' => 'Abastecida',
                        'class' => 'badge-success text-white',
                        'icon' => 'fas fa-warehouse',
                    ],
                    self::STATUS_CANCELLED => [
                        'label' => 'Cancelada',
                        'class' => 'badge-danger text-white',
                        'icon' => 'fas fa-times-circle',
                    ],
                    self::STATUS_DELIVERED => [
                        'label' => 'Entregada',
                        'class' => 'badge-primary text-white',
                        'icon' => 'fas fa-truck',
                    ],
                    self::STATUS_INVOICED => [
                        'label' => 'Facturada',
                        'class' => 'badge-info text-white',
                        'icon' => 'fas fa-file-invoice-dollar',
                    ],
                ];

                $status = $statuses[$order->status] ?? [
                    'label' => ucfirst((string) $order->status),
                    'class' => 'badge-light text-dark border',
                    'icon' => 'fas fa-info-circle',
                ];

                return sprintf(
                    '<div class="d-flex justify-content-center">
                        <span class="badge %s rounded-pill px-3 py-2 shadow-sm font-weight-bold"
                            style="min-width:120px;font-size:11px;letter-spacing:.2px;">
                            <i class="%s mr-1" aria-hidden="true"></i>
                            %s
                        </span>
                    </div>',
                    $status['class'],
                    $status['icon'],
                    e($status['label'])
                );
            })
            ->editColumn('created_at', function (CustomerPurchaseOrder $order) {
                return $order->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '-';
            })
            ->addColumn('acciones', function (CustomerPurchaseOrder $order) {
                return view(
                    'admin.customer-purchase-orders.partials.acciones',
                    compact('order')
                )->render();
            })
            ->rawColumns(['customer', 'delivery_period', 'status', 'acciones'])
            ->make(true);
    }

    private function deliveryPeriodHtml(CustomerPurchaseOrder $order): string
    {
        $start = $order->delivery_start_date;
        $end = $order->delivery_end_date;

        if (!$start || !$end) {
            return '<div class="delivery-period-card delivery-period-muted">
                <span class="delivery-period-badge">Sin plazo definido</span>
            </div>';
        }

        $configuredDays = $order->delivery_days
            ?? ((int) $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()) + 1);
        $remainingDays = (int) today()->startOfDay()->diffInDays($end->copy()->startOfDay(), false);
        $dayLabel = fn (int $days) => $days === 1 ? 'día' : 'días';
        $visualState = 'success';
        $message = '';

        if ($order->status === self::STATUS_ENTERED) {
            $message = 'Entrega completada';
        } elseif ($order->status === self::STATUS_PARTIAL_ENTERED) {
            if ($remainingDays < 0) {
                $visualState = 'danger';
                $expiredDays = abs($remainingDays);
                $message = "Ingreso parcial · vencido hace {$expiredDays} {$dayLabel($expiredDays)}";
            } elseif ($remainingDays === 0) {
                $visualState = 'warning';
                $message = 'Ingreso parcial · vence hoy';
            } elseif ($remainingDays <= 5) {
                $visualState = 'warning';
                $message = "Ingreso parcial · vence en {$remainingDays} {$dayLabel($remainingDays)}";
            } else {
                $visualState = 'info';
                $message = "Ingreso parcial · vence en {$remainingDays} {$dayLabel($remainingDays)}";
            }
        } elseif ($order->status === self::STATUS_CANCELLED) {
            $visualState = 'muted';
            $message = 'Cancelada';
        } elseif ($remainingDays < 0) {
            $visualState = 'danger';
            $expiredDays = abs($remainingDays);
            $message = "Vencido hace {$expiredDays} {$dayLabel($expiredDays)}";
        } elseif ($remainingDays === 0) {
            $visualState = 'warning';
            $message = 'Vence hoy';
        } elseif ($remainingDays <= 5) {
            $visualState = 'warning';
            $message = "Vence en {$remainingDays} {$dayLabel($remainingDays)}";
        } else {
            $message = "Vence en {$remainingDays} {$dayLabel($remainingDays)}";
        }

        return sprintf(
            '<div class="delivery-period-card delivery-period-%s">
                <div><strong>Desde:</strong> %s</div>
                <div><strong>Hasta:</strong> %s</div>
                <div class="delivery-period-days"><strong>Plazo:</strong> %d %s calendario</div>
                <span class="delivery-period-badge">%s</span>
            </div>',
            $visualState,
            e($start->format('d/m/Y')),
            e($end->format('d/m/Y')),
            $configuredDays,
            $dayLabel($configuredDays),
            e($message)
        );
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

    public function searchCustomers(Request $request)
    {
        $search = trim((string) $request->input('q', $request->input('term', '')));

        $customers = Customer::query()
            ->where('status', true)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($subquery) use ($search) {
                    $subquery
                        ->where('document_number', 'like', "%{$search}%")
                        ->orWhere('ruc', 'like', "%{$search}%")
                        ->orWhere('business_name', 'like', "%{$search}%")
                        ->orWhere('full_name', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                });
            })
            ->orderBy('business_name')
            ->orderBy('full_name')
            ->limit(30)
            ->get();

        return response()->json([
            'results' => $customers
                ->map(fn (Customer $customer) => $this->customerSelectPayload($customer))
                ->values(),
        ]);
    }

    public function quickStoreCustomer(Request $request)
    {
        $validated = $request->validate([
            'person_type' => ['required', Rule::in(['natural', 'juridica'])],
            'document_type' => ['required', Rule::in(['DNI', 'RUC'])],
            'document_number' => [
                'required',
                'digits_between:8,11',
                function ($attribute, $value, $fail) use ($request) {
                    $documentType = mb_strtoupper((string) $request->input('document_type'));

                    if ($documentType === 'RUC' && !preg_match('/^\d{11}$/', (string) $value)) {
                        $fail('El RUC debe tener 11 dígitos.');
                        return;
                    }

                    if ($documentType === 'DNI' && !preg_match('/^\d{8}$/', (string) $value)) {
                        $fail('El DNI debe tener 8 dígitos.');
                        return;
                    }

                },
            ],
            'business_name' => ['required', 'string', 'max:255'],
            'channel' => ['nullable', 'string', 'max:100'],
            'subchannel' => ['nullable', 'string', 'max:100'],
            'withholding_agent' => ['nullable', 'boolean'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'boolean'],
        ], [
            'person_type.required' => 'El tipo de persona es obligatorio.',
            'person_type.in' => 'El tipo de persona seleccionado no es válido.',
            'document_type.required' => 'El tipo de documento es obligatorio.',
            'document_type.in' => 'El tipo de documento seleccionado no es válido.',
            'document_number.required' => 'El número de documento es obligatorio.',
            'document_number.digits_between' => 'El número de documento debe tener 8 u 11 dígitos.',
            'business_name.required' => 'La razón social o nombre es obligatorio.',
            'business_name.max' => 'La razón social o nombre no debe exceder 255 caracteres.',
            'email.email' => 'El correo no tiene un formato válido.',
        ]);

        try {
            $documentType = mb_strtoupper($validated['document_type']);
            $documentNumber = (string) $validated['document_number'];
            $existingCustomer = $this->findExistingCustomer($documentType, $documentNumber);

            if ($existingCustomer) {
                $branch = $this->mainBranchForCustomer($existingCustomer);

                return response()->json([
                    'success' => false,
                    'duplicate' => true,
                    'message' => 'Este cliente ya está registrado.',
                    'customer' => $this->customerSelectPayload($existingCustomer),
                    'branch' => $branch ? $this->branchSelectPayload($branch) : null,
                ], 409);
            }

            $payload = DB::transaction(function () use ($validated, $documentType, $documentNumber) {
                $name = $this->upperOrNull($validated['business_name']);
                $isRuc = $documentType === 'RUC';
                $userId = Auth::id();

                $customer = Customer::create([
                    'person_type' => $validated['person_type'],
                    'document_type' => $documentType,
                    'document_number' => $documentNumber,
                    'ruc' => $isRuc ? $documentNumber : null,
                    'business_name' => $isRuc ? $name : null,
                    'first_name' => $isRuc ? null : $name,
                    'last_name' => $isRuc ? null : '',
                    'full_name' => $name,
                    'channel' => $this->upperOrNull($validated['channel'] ?? null),
                    'subchannel' => $this->upperOrNull($validated['subchannel'] ?? null),
                    'withholding_agent' => (bool) ($validated['withholding_agent'] ?? false),
                    'phone' => $validated['phone'] ?? null,
                    'email' => $validated['email'] ?? null,
                    'address' => $this->upperOrNull($validated['address'] ?? null),
                    'status' => (bool) ($validated['status'] ?? true),
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);

                $branch = CustomerBranch::create([
                    'customer_id' => $customer->id,
                    'branch_name' => 'SEDE PRINCIPAL',
                    'address' => $this->upperOrNull($validated['address'] ?? null),
                    'is_main' => true,
                    'status' => true,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);

                return [
                    'customer' => $customer,
                    'branch' => $branch,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Cliente registrado correctamente.',
                'customer' => $this->customerSelectPayload($payload['customer']),
                'branch' => $this->branchSelectPayload($payload['branch']),
            ], 201);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Error quick storing customer from customer purchase order: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'No se pudo guardar el cliente.',
            ], 500);
        }
    }

    public function quoteItems(Quote $quote)
    {
        if (!in_array($quote->status, [
            Quote::STATUS_SENT,
            Quote::STATUS_APPROVED,
        ], true)) {
            throw ValidationException::withMessages([
                'quote_id' => 'La cotización seleccionada no está disponible para generar una orden de compra.',
            ]);
        }

        $quote->load([
            'items.article',
            'items.unit',
            'items.presentation',
            'items.brand',
        ]);

        $affectIgv = (bool) $quote->affect_igv;

        $items = $quote->items->map(function (QuoteItem $item) use ($affectIgv) {
            $quantity = (string) $item->quantity;
            $unitPrice = (string) $item->unit_price;
            $lineTotal = bcmul($quantity, $unitPrice, 10);
            $subtotal = $affectIgv ? bcdiv($lineTotal, '1.18', 10) : $lineTotal;
            $taxAmount = $affectIgv ? bcsub($lineTotal, $subtotal, 10) : '0';

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
                'line_total' => $lineTotal,
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
            'documents.documentType',
            'items.quoteItem',
            'items.marketStudyItem',
            'items.article',
            'items.unit',
            'items.presentation',
            'items.brand',
        ]);
        $this->appendSupplyProgress($customerPurchaseOrder);
        $customerPurchaseOrder->documents->each(function (Document $document) {
            $document->setAttribute('url', Storage::disk('public')->url($document->file_path));
        });

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
        try {
            DB::transaction(function () use ($customerPurchaseOrder) {
                $order = CustomerPurchaseOrder::query()
                    ->whereKey($customerPurchaseOrder->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $quoteId = $order->quote_id;

                $order->delete();

                if (!$quoteId) {
                    return;
                }

                $quote = Quote::query()
                    ->whereKey($quoteId)
                    ->lockForUpdate()
                    ->first();

                if (!$quote || $quote->status !== Quote::STATUS_APPROVED) {
                    return;
                }

                $hasOtherActiveOrders = CustomerPurchaseOrder::query()
                    ->where('quote_id', $quoteId)
                    ->exists();

                if ($hasOtherActiveOrders) {
                    return;
                }

                $isExpired = $quote->validity_date
                    && today()->gt($quote->validity_date);

                $quote->update([
                    'status' => $isExpired
                        ? Quote::STATUS_EXPIRED
                        : Quote::STATUS_SENT,
                ]);
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Orden de compra de cliente eliminada correctamente.',
            ]);
        } catch (\Throwable $e) {
            Log::error(
                'Error deleting customer purchase order: ' . $e->getMessage()
            );

            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo eliminar la orden de compra de cliente.',
            ], 500);
        }
    }

    private function saveOrder(
        Request $request,
        ?CustomerPurchaseOrder $order = null
    ) {
        if ($order === null) {
            $request->merge(['status' => self::STATUS_REGISTERED]);
        }

        $validated = $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
            'quote_id' => [
                'nullable',
                Rule::exists('quotes', 'id')
                    ->where('customer_id', $request->input('customer_id'))
                    ->whereIn('status', [
                        Quote::STATUS_SENT,
                        Quote::STATUS_APPROVED,
                    ])
                    ->whereNull('deleted_at'),
            ],
            'customer_id' => ['required', 'exists:customers,id'],
            'customer_branch_id' => [
                'nullable',
                Rule::exists('customer_branches', 'id')
                    ->where('customer_id', $request->input('customer_id')),
            ],
            'order_type' => ['required', Rule::in(['articles', 'services'])],
            'purchase_order_number' => ['required', 'string', 'max:100'],
            'currency_id' => ['required', 'exists:currencies,id'],
            'notification_date' => ['nullable', 'date', 'required_with:delivery_days'],
            'delivery_start_date' => ['nullable', 'date'],
            'delivery_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'delivery_end_date' => ['nullable', 'date'],
            'siaf_file_number' => ['nullable', 'string', 'max:100'],
            'acquisition_chart_number' => ['nullable', 'string', 'max:100'],
            'process_type' => ['nullable', 'string', 'max:100'],
            'billing_type' => ['required', Rule::in(['local', 'export'])],
            'affect_igv' => ['required', 'boolean'],
            'observations' => ['nullable', 'string'],
            'status' => [
                'nullable',
                Rule::in($this->customerPurchaseOrderStatuses()),
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
            'documents' => ['nullable', 'array'],
            'documents.*.file' => [
                'required',
                'file',
                'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
                'max:10240',
            ],
            'documents.*.document_type_id' => ['nullable', 'exists:document_types,id'],
            'deleted_documents' => ['nullable', 'array'],
            'deleted_documents.*' => ['integer'],
        ], [
            'purchase_order_number.required' => 'El N° de Orden de Compra es obligatorio.',
        ]);

        $storedDocumentPaths = [];

        try {
            return DB::transaction(function () use ($validated, $order, $request, &$storedDocumentPaths) {
                $isCreating = $order === null;
                $affectIgv = (bool) ($validated['affect_igv'] ?? false);
                $preparedItems = $this->prepareItems($validated['items'], $affectIgv);
                $totals = $this->calculateTotals($preparedItems, $affectIgv);
                $deliveryStartDate = null;
                $deliveryEndDate = null;

                if (!empty($validated['notification_date'])) {
                    $notificationDate = Carbon::parse($validated['notification_date']);
                    $deliveryStartDate = $notificationDate->copy()->addDay()->toDateString();
                    $deliveryEndDate = !empty($validated['delivery_days'])
                        ? $notificationDate->copy()->addDays((int) $validated['delivery_days'])->toDateString()
                        : null;
                }

                $relatedQuote = null;

                if ($isCreating && !empty($validated['quote_id'])) {
                    $relatedQuote = Quote::query()
                        ->whereKey($validated['quote_id'])
                        ->whereIn('status', [
                            Quote::STATUS_SENT,
                            Quote::STATUS_APPROVED,
                        ])
                        ->lockForUpdate()
                        ->first();

                    if (!$relatedQuote) {
                        throw ValidationException::withMessages([
                            'quote_id' => 'La cotización ya no está disponible para generar una orden de compra.',
                        ]);
                    }
                }

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
                    'delivery_start_date' => $deliveryStartDate,
                    'delivery_days' => $validated['delivery_days'] ?? null,
                    'delivery_end_date' => $deliveryEndDate,
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
                    'status' => $isCreating
                        ? self::STATUS_REGISTERED
                        : ($validated['status'] ?? $order->status ?? self::STATUS_REGISTERED),
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

                $this->deleteOrderDocuments(
                    $order,
                    $validated['deleted_documents'] ?? []
                );
                $this->storeOrderDocuments(
                    $order,
                    $request->file('documents', []),
                    $validated['documents'] ?? [],
                    $storedDocumentPaths
                );

                if ($relatedQuote && $relatedQuote->status === Quote::STATUS_SENT) {
                    $relatedQuote->update([
                        'status' => Quote::STATUS_APPROVED,
                    ]);
                }

                return response()->json([
                    'status' => 'success',
                    'message' => $order->wasRecentlyCreated
                        ? 'Orden de compra de cliente registrada correctamente.'
                        : 'Orden de compra de cliente actualizada correctamente.',
                    'data' => $order->fresh(['items', 'documents.documentType']),
                ], $order->wasRecentlyCreated ? 201 : 200);
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            foreach ($storedDocumentPaths as $path) {
                Storage::disk('public')->delete($path);
            }

            Log::error('Error saving customer purchase order: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo guardar la orden de compra de cliente.',
            ], 500);
        }
    }

    private function storeOrderDocuments(
        CustomerPurchaseOrder $order,
        array $uploadedDocuments,
        array $documentData,
        array &$storedPaths
    ): void {
        foreach ($uploadedDocuments as $index => $upload) {
            $file = is_array($upload) ? ($upload['file'] ?? null) : null;

            if (!$file) {
                continue;
            }

            $storedName = Str::uuid() . '.' . strtolower($file->getClientOriginalExtension());
            $path = $file->storeAs('customer-purchase-orders/documents', $storedName, 'public');
            $storedPaths[] = $path;
            $meta = $documentData[$index] ?? [];

            $order->documents()->create([
                'document_type_id' => $meta['document_type_id'] ?? null,
                'original_name' => $file->getClientOriginalName(),
                'stored_name' => $storedName,
                'file_path' => $path,
                'mime_type' => $file->getMimeType(),
                'extension' => strtolower($file->getClientOriginalExtension()),
                'file_size' => $file->getSize() ?: 0,
                'issue_date' => null,
                'expiration_date' => null,
                'status' => 'ACTIVE',
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);
        }
    }

    private function deleteOrderDocuments(CustomerPurchaseOrder $order, array $documentIds): void
    {
        if (empty($documentIds)) {
            return;
        }

        $documents = $order->documents()->whereKey($documentIds)->lockForUpdate()->get();

        foreach ($documents as $document) {
            $path = $document->file_path;
            $document->update(['deleted_by' => Auth::id()]);
            $document->delete();

            DB::afterCommit(function () use ($path) {
                if ($path && Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                }
            });
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

                $quantity = (string) $item['quantity'];
                $unitPrice = (string) $item['unit_price'];
                $lineTotal = bcmul($quantity, $unitPrice, 10);
                $subtotal = $affectIgv ? bcdiv($lineTotal, '1.18', 10) : $lineTotal;
                $taxAmount = $affectIgv ? bcsub($lineTotal, $subtotal, 10) : '0';

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
                    'line_total' => $lineTotal,
                    'status' => $item['status'] ?? 'active',
                ];
            })
            ->all();
    }

    private function calculateTotals(array $items, bool $affectIgv): array
    {
        $grandTotal = array_reduce(
            $items,
            fn (string $total, array $item) => bcadd($total, (string) $item['line_total'], 10),
            '0'
        );
        $subtotalTaxed = $affectIgv ? bcdiv($grandTotal, '1.18', 10) : '0';
        $igv = $affectIgv ? bcsub($grandTotal, $subtotalTaxed, 10) : '0';

        return [
            'subtotal_exonerated' => $affectIgv ? '0' : $grandTotal,
            'subtotal_taxed' => $subtotalTaxed,
            'igv' => $igv,
            'grand_total' => $grandTotal,
        ];
    }

    private function formatDecimal(string|int|float|null $value): string
    {
        return number_format((float) ($value ?? 0), 3, '.', '');
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

    private function customerPurchaseOrderStatuses(): array
    {
        return [
            self::STATUS_REGISTERED,
            self::STATUS_IN_PURCHASE,
            self::STATUS_PARTIAL_ENTERED,
            self::STATUS_ENTERED,
            self::STATUS_CANCELLED,
            self::STATUS_DELIVERED,
            self::STATUS_INVOICED,
        ];
    }

    private function upperOrNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? mb_strtoupper($value) : null;
    }

    private function findExistingCustomer(string $documentType, string $documentNumber): ?Customer
    {
        return Customer::query()
            ->where(function ($query) use ($documentType, $documentNumber) {
                $query->where(function ($subquery) use ($documentType, $documentNumber) {
                    $subquery
                        ->where('document_type', $documentType)
                        ->where('document_number', $documentNumber);
                });

                if ($documentType === 'RUC') {
                    $query->orWhere('ruc', $documentNumber);
                }
            })
            ->first();
    }

    private function mainBranchForCustomer(Customer $customer): ?CustomerBranch
    {
        return CustomerBranch::query()
            ->where('customer_id', $customer->id)
            ->where('status', true)
            ->orderByDesc('is_main')
            ->orderBy('branch_name')
            ->first();
    }

    private function customerSelectPayload(Customer $customer): array
    {
        $name = $customer->business_name
            ?? $customer->full_name
            ?? trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? ''))
            ?: 'Cliente';
        $document = $customer->document_number ?? $customer->ruc;

        return [
            'id' => $customer->id,
            'text' => trim(($document ? $document . ' | ' : '') . $name),
        ];
    }

    private function branchSelectPayload(CustomerBranch $branch): array
    {
        return [
            'id' => $branch->id,
            'text' => $branch->branch_name,
            'address' => $branch->address,
        ];
    }

    private function appendSupplyProgress(CustomerPurchaseOrder $order): void
    {
        $itemIds = $order->items
            ->where('status', '!=', 'deleted')
            ->pluck('id')
            ->all();

        if (empty($itemIds)) {
            return;
        }

        $purchaseByItem = DB::table('supplier_purchase_order_items as items')
            ->join('supplier_purchase_orders as orders', 'orders.id', '=', 'items.supplier_purchase_order_id')
            ->whereIn('items.customer_purchase_order_item_id', $itemIds)
            ->whereNull('orders.deleted_at')
            ->where('orders.status', '!=', 'cancelled')
            ->where('items.status', '!=', 'deleted')
            ->groupBy('items.customer_purchase_order_item_id')
            ->selectRaw('items.customer_purchase_order_item_id, SUM(items.quantity) as purchase_quantity')
            ->pluck('purchase_quantity', 'customer_purchase_order_item_id');

        $enteredByItem = DB::table('warehouse_entry_items as entry_items')
            ->join('warehouse_entries as entries', 'entries.id', '=', 'entry_items.warehouse_entry_id')
            ->join('supplier_purchase_order_items as supplier_items', 'supplier_items.id', '=', 'entry_items.supplier_purchase_order_item_id')
            ->join('supplier_purchase_orders as supplier_orders', 'supplier_orders.id', '=', 'supplier_items.supplier_purchase_order_id')
            ->whereIn('supplier_items.customer_purchase_order_item_id', $itemIds)
            ->whereNull('entries.deleted_at')
            ->whereNull('supplier_orders.deleted_at')
            ->where('entries.status', 'registered')
            ->where('supplier_orders.status', '!=', 'cancelled')
            ->where('entry_items.status', '!=', 'deleted')
            ->where('supplier_items.status', '!=', 'deleted')
            ->groupBy('supplier_items.customer_purchase_order_item_id')
            ->selectRaw('supplier_items.customer_purchase_order_item_id, SUM(entry_items.quantity) as entered_quantity')
            ->pluck('entered_quantity', 'customer_purchase_order_item_id');

        $order->items->each(function ($item) use ($purchaseByItem, $enteredByItem) {
            $requested = round((float) $item->quantity, 2);
            $purchase = round((float) ($purchaseByItem[$item->id] ?? 0), 2);
            $entered = round((float) ($enteredByItem[$item->id] ?? 0), 2);
            $pending = max(round($requested - $entered, 2), 0);
            $status = match (true) {
                $purchase <= 0 => 'registered',
                $entered <= 0 => 'in_purchase',
                $pending <= 0 => 'entered',
                default => 'partial_entered',
            };

            $item->setAttribute('requested_quantity', $requested);
            $item->setAttribute('purchase_quantity', $purchase);
            $item->setAttribute('entered_quantity', $entered);
            $item->setAttribute('pending_quantity', $pending);
            $item->setAttribute('supply_status', $status);
        });
    }
}
