<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Article;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerBranch;
use App\Models\Company;
use App\Models\Currency;
use App\Models\MarketStudy;
use App\Models\Unit;
use App\Models\Presentation;
use App\Models\Brand;
use Barryvdh\DomPDF\Facade\Pdf;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Validation\ValidationException;

class QuoteController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:admin.quotes.index')->only([
            'index',
            'list',
            'generateNumber',
            'customerBranches',
            'marketStudyWinners',
            'searchMarketStudies',
            'searchArticles',
            'searchBrands',
            'searchCustomers',
        ]);
        $this->middleware('can:admin.quotes.store')->only([
            'store',
            'quickStoreArticle',
            'quickStoreBrand',
            'generateArticleCode',
            'quickStoreCustomer',
        ]);
        $this->middleware('can:admin.quotes.update')->only(['update']);
        $this->middleware('can:admin.quotes.destroy')->only(['destroy']);
    }

    public function list()
    {
        Quote::dismissExpiredQuotes();

        $quotes = Quote::query()
            ->with([
                'customer:id,business_name,full_name,first_name,last_name',
                'company:id,business_name,trade_name',
                'currency:id,code,symbol,description',
                'documents' => function ($query) {
                    $query->where('observation', 'PDF_GENERATED_QUOTE')
                        ->where('status', 'ACTIVE')
                        ->where('mime_type', 'application/pdf')
                        ->latest('id');
                },
            ])
            ->orderByDesc('id');

        return DataTables::of($quotes)
            ->addIndexColumn()
            ->addColumn('customer', function (Quote $quote) {
                return $quote->customer?->business_name
                    ?? $quote->customer?->full_name
                    ?? trim(($quote->customer?->first_name ?? '') . ' ' . ($quote->customer?->last_name ?? ''))
                    ?: '-';
            })
            ->addColumn('company', function (Quote $quote) {
                return $quote->company?->trade_name
                    ?? $quote->company?->business_name
                    ?? '-';
            })
            ->addColumn('currency', function (Quote $quote) {
                return $quote->currency?->code
                    ?? $quote->currency?->description
                    ?? '-';
            })
            ->editColumn('grand_total', function (Quote $quote) {
                $symbol = $quote->currency?->symbol ?? '';

                return trim($symbol . ' ' . number_format((float) $quote->grand_total, 2));
            })
            ->editColumn('status', function (Quote $quote) {
                $statuses = [
                    Quote::STATUS_DRAFT => [
                        'label' => 'Borrador',
                        'class' => 'badge-secondary text-white',
                        'icon' => 'fas fa-pencil-alt',
                    ],
                    Quote::STATUS_SENT => [
                        'label' => 'Emitida',
                        'class' => 'badge-info text-white',
                        'icon' => 'fas fa-paper-plane',
                    ],
                    Quote::STATUS_APPROVED => [
                        'label' => 'Aprobada',
                        'class' => 'badge-success text-white',
                        'icon' => 'fas fa-check-circle',
                    ],
                    Quote::STATUS_REJECTED => [
                        'label' => 'Rechazada',
                        'class' => 'badge-danger text-white',
                        'icon' => 'fas fa-times-circle',
                    ],
                    Quote::STATUS_EXPIRED => [
                        'label' => 'Desestimado',
                        'class' => 'badge-warning text-dark',
                        'icon' => 'fas fa-ban',
                    ],
                    Quote::STATUS_AWARDED => [
                        'label' => 'Adjudicada',
                        'class' => 'badge-primary text-white',
                        'icon' => 'fas fa-award',
                    ],
                ];

                $status = $statuses[$quote->status] ?? [
                    'label' => ucfirst((string) $quote->status),
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
            ->editColumn('created_at', function (Quote $quote) {
                return $quote->created_at
                    ? $quote->created_at->timezone(config('app.timezone'))->format('d/m/Y H:i')
                    : '-';
            })
            ->addColumn('acciones', function (Quote $quote) {
                $pdfDocument = $quote->documents
                    ->first(fn (Document $document) => $document->file_path
                        && Storage::disk('public')->exists($document->file_path));

                $pdfUrl = $pdfDocument
                    ? Storage::disk('public')->url($pdfDocument->file_path)
                    : null;

                return view('admin.quotes.partials.acciones', compact('quote', 'pdfUrl'))->render();
            })
            ->rawColumns(['status', 'acciones'])
            ->make(true);
    }

    public function index()
    {
        Quote::dismissExpiredQuotes();

        $customers = Customer::query()
            ->orderBy('business_name')
            ->orderBy('full_name')
            ->get();

        $companies = Company::query()
            ->where('status', 1)
            ->orderBy('business_name')
            ->get();

        $currencies = Currency::query()
            ->where('status', 'ACTIVE')
            ->orderBy('description')
            ->get();

        $marketStudies = MarketStudy::query()
            ->where('status', 1)
            ->orderByDesc('created_at')
            ->get();

        $units = Unit::query()
            ->orderBy('description')
            ->get();

        $presentations = Presentation::query()
            ->orderBy('description')
            ->get();

        $brands = Brand::query()
            ->orderBy('description')
            ->get();

        return view('admin.quotes.index', compact(
            'customers',
            'companies',
            'currencies',
            'marketStudies',
            'units',
            'presentations',
            'brands'
        ));
    }

    public function generateNumber()
    {
        return response()->json([
            'quote_number' => $this->nextQuoteNumber(),
        ]);
    }

    public function customerBranches($customerId)
    {
        $branches = CustomerBranch::query()
            ->where('customer_id', $customerId)
            ->where('status', 1)
            ->orderByDesc('is_main')
            ->orderBy('branch_name')
            ->get([
                'id',
                'customer_id',
                'branch_name',
                'branch_type',
                'phone',
                'email',
                'ubigeo_id',
                'address',
                'reference',
                'voucher_type',
                'generate_guide',
                'payment_condition',
                'is_main',
                'status',
            ]);

        return response()->json([
            'branches' => $branches
        ]);
    }

    public function searchCustomers(Request $request)
    {
        $term = trim((string) $request->get('q', ''));

        $customers = Customer::query()
            ->when($term !== '', function ($query) use ($term) {
                $query->where(function ($q) use ($term) {
                    $q->where('document_number', 'like', "%{$term}%")
                        ->orWhere('ruc', 'like', "%{$term}%")
                        ->orWhere('business_name', 'like', "%{$term}%")
                        ->orWhere('full_name', 'like', "%{$term}%")
                        ->orWhere('first_name', 'like', "%{$term}%")
                        ->orWhere('last_name', 'like', "%{$term}%");
                });
            })
            ->orderBy('business_name')
            ->orderBy('full_name')
            ->limit(30)
            ->get(['id', 'document_number', 'ruc', 'business_name', 'full_name', 'first_name', 'last_name']);

        return response()->json([
            'results' => $customers->map(fn (Customer $customer) => [
                'id' => $customer->id,
                'text' => $this->quoteCustomerText($customer),
            ]),
        ]);
    }

    public function quickStoreCustomer(Request $request)
    {
        $documentType = mb_strtoupper(trim((string) $request->input('document_type')));
        $documentNumber = preg_replace('/\D+/', '', (string) $request->input('document_number'));
        $name = trim((string) $request->input('name'));

        $request->merge([
            'document_type' => $documentType,
            'document_number' => $documentNumber,
            'name' => $name,
            'status' => 1,
            'withholding_agent' => $request->boolean('withholding_agent') ? 1 : 0,
        ]);

        $validated = $request->validate([
            'person_type' => ['required', Rule::in(['natural', 'juridica'])],
            'document_type' => ['required', Rule::in(['DNI', 'CE', 'RUC'])],
            'document_number' => [
                'required',
                'string',
                'max:20',
                function ($attribute, $value, $fail) use ($documentType) {
                    if ($documentType === 'RUC' && strlen((string) $value) !== 11) {
                        $fail('El RUC debe tener 11 dígitos.');
                    }

                    if ($documentType === 'DNI' && strlen((string) $value) !== 8) {
                        $fail('El DNI debe tener 8 dígitos.');
                    }
                },
            ],
            'name' => ['required', 'string', 'max:255'],
            'channel' => ['nullable', 'string', 'max:100'],
            'subchannel' => ['nullable', 'string', 'max:100'],
            'withholding_agent' => ['nullable', 'boolean'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
        ], [
            'person_type.required' => 'El tipo de persona es obligatorio.',
            'document_type.required' => 'El tipo de documento es obligatorio.',
            'document_number.required' => 'El número de documento es obligatorio.',
            'name.required' => 'La razón social o nombre es obligatorio.',
            'email.email' => 'El email no tiene un formato válido.',
        ]);

        $existingCustomer = Customer::query()
            ->where('document_type', $validated['document_type'])
            ->where('document_number', $validated['document_number'])
            ->first();

        if (!$existingCustomer && $validated['document_type'] === 'RUC') {
            $existingCustomer = Customer::query()
                ->where('ruc', $validated['document_number'])
                ->first();
        }

        if ($existingCustomer) {
            $branch = $this->mainBranchForCustomer($existingCustomer);

            return response()->json([
                'success' => false,
                'message' => 'Este cliente ya está registrado.',
                'customer' => [
                    'id' => $existingCustomer->id,
                    'text' => $this->quoteCustomerText($existingCustomer),
                ],
                'branch' => $branch ? $this->quoteBranchPayload($branch) : null,
            ], 409);
        }

        try {
            DB::beginTransaction();

            $upperName = mb_strtoupper($validated['name'], 'UTF-8');
            $address = $this->upperOrNull($validated['address'] ?? null);

            $customerData = [
                'person_type' => $validated['person_type'],
                'document_type' => $validated['document_type'],
                'document_number' => $validated['document_number'],
                'ruc' => $validated['document_type'] === 'RUC' ? $validated['document_number'] : null,
                'business_name' => $validated['document_type'] === 'RUC' ? $upperName : null,
                'first_name' => $validated['document_type'] === 'RUC' ? null : $upperName,
                'last_name' => $validated['document_type'] === 'RUC' ? null : '',
                'full_name' => $upperName,
                'channel' => $this->upperOrNull($validated['channel'] ?? null),
                'subchannel' => $this->upperOrNull($validated['subchannel'] ?? null),
                'withholding_agent' => (int) ($validated['withholding_agent'] ?? 0),
                'phone' => $validated['phone'] ?? null,
                'email' => $validated['email'] ?? null,
                'address' => $address,
                'status' => 1,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ];

            $customer = Customer::create($customerData);

            $branch = CustomerBranch::create([
                'customer_id' => $customer->id,
                'branch_name' => 'SEDE PRINCIPAL',
                'branch_type' => 'PRINCIPAL',
                'phone' => $validated['phone'] ?? null,
                'email' => $validated['email'] ?? null,
                'address' => $address,
                'reference' => null,
                'voucher_type' => null,
                'generate_guide' => 'NO',
                'payment_condition' => null,
                'is_main' => 1,
                'status' => 1,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Cliente registrado correctamente.',
                'customer' => [
                    'id' => $customer->id,
                    'text' => $this->quoteCustomerText($customer),
                ],
                'branch' => $this->quoteBranchPayload($branch),
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Error quick creating quote customer: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'No se pudo guardar el cliente.',
            ], 500);
        }
    }

    public function marketStudyWinners($marketStudyId)
    {
        $items = DB::table('market_study_item_winners as winners')
            ->join('market_study_items as study_items', 'study_items.id', '=', 'winners.market_study_item_id')
            ->join('market_study_quote_items as quote_items', 'quote_items.id', '=', 'winners.market_study_quote_item_id')
            ->leftJoin('articles as articles', 'articles.id', '=', 'quote_items.article_id')
            ->leftJoin('brands as brands', 'brands.id', '=', 'quote_items.brand_id')
            ->leftJoin('units as units', 'units.id', '=', 'quote_items.unit_id')
            ->leftJoin('presentations as presentations', 'presentations.id', '=', 'quote_items.presentation_id')
            ->where('study_items.market_study_id', $marketStudyId)
            ->select([
                'winners.id as winner_id',

                'study_items.id as market_study_item_id',
                'study_items.article_id as study_article_id',
                'study_items.article_code_snapshot',
                'study_items.billing_name_snapshot',
                'study_items.presentation_snapshot',
                'study_items.cost_condition_snapshot',

                'quote_items.id as market_study_quote_item_id',
                'quote_items.article_id',
                'quote_items.brand_id',
                'quote_items.unit_id',
                'quote_items.presentation_id',
                'quote_items.expiration_date',
                'quote_items.origin',
                'quote_items.quantity',
                'quote_items.unit_price',
                'quote_items.subtotal',
                'quote_items.tax_amount',
                'quote_items.total',

                'articles.code as article_code',
                'articles.billing_name as article_billing_name',
                'brands.description as brand_name',
                'units.description as unit_name',
                'presentations.description as presentation_name',
            ])
            ->orderBy('study_items.id')
            ->get()
            ->map(function ($item) {

                $quantity = (float) ($item->quantity ?? 0);
                $unitPrice = (float) ($item->unit_price ?? 0);
                $lineTotal = $quantity * $unitPrice;

                return [
                    'winner_id' => $item->winner_id,

                    'market_study_item_id' => $item->market_study_item_id,
                    'market_study_quote_item_id' => $item->market_study_quote_item_id,

                    'article_id' => $item->article_id ?? $item->study_article_id,
                    'article_code' => $item->article_code_snapshot ?? $item->article_code,
                    'billing_name_snapshot' => $item->billing_name_snapshot ?? $item->article_billing_name,

                    'unit_id' => $item->unit_id,
                    'presentation_id' => $item->presentation_id,
                    'brand_id' => $item->brand_id,

                    'origin' => $item->origin,
                    'expiration_date' => $item->expiration_date,

                    'cost_type' => $item->cost_condition_snapshot ?? 'PESO',
                    'cost_price' => number_format($unitPrice, 2, '.', ''),

                    'quantity' => number_format($quantity, 2, '.', ''),
                    'unit_price' => number_format($unitPrice, 2, '.', ''),

                    'discount_percentage' => '0.00',
                    'discount_amount' => '0.00',
                    'line_total' => number_format($lineTotal, 2, '.', ''),

                    'is_winner' => 1,
                ];
            });

        if ($items->isEmpty()) {
            $items = DB::table('market_study_items as study_items')
                ->leftJoin('articles as articles', 'articles.id', '=', 'study_items.article_id')
                ->leftJoin('units as units', 'units.id', '=', 'articles.unit_id')
                ->leftJoin('presentations as presentations', 'presentations.id', '=', 'articles.presentation_id')
                ->leftJoin('brands as brands', 'brands.id', '=', 'articles.brand_id')
                ->where('study_items.market_study_id', $marketStudyId)
                ->select([
                    'study_items.id as market_study_item_id',
                    'study_items.article_id',
                    'study_items.article_code_snapshot',
                    'study_items.billing_name_snapshot',
                    'study_items.cost_condition_snapshot',
                    'articles.code as article_code',
                    'articles.billing_name as article_billing_name',
                    'articles.unit_id',
                    'articles.presentation_id',
                    'articles.brand_id',
                    'units.description as unit_name',
                    'presentations.description as presentation_name',
                    'brands.description as brand_name',
                ])
                ->orderBy('study_items.id')
                ->get()
                ->map(function ($item) {
                    return [
                        'market_study_item_id' => $item->market_study_item_id,
                        'article_id' => $item->article_id,
                        'article_code' => $item->article_code_snapshot ?? $item->article_code,
                        'billing_name_snapshot' => $item->billing_name_snapshot ?? $item->article_billing_name,
                        'unit_id' => $item->unit_id,
                        'presentation_id' => $item->presentation_id,
                        'brand_id' => $item->brand_id,
                        'origin' => '',
                        'expiration_date' => null,
                        'cost_type' => $item->cost_condition_snapshot ?? 'PESO',
                        'cost_price' => '0.00',
                        'quantity' => '1.00',
                        'unit_price' => '0.00',
                        'discount_percentage' => '0.00',
                        'discount_amount' => '0.00',
                        'line_total' => '0.00',
                        'is_winner' => 0,
                    ];
                });
        }

        return response()->json([
            'items' => $items,
        ]);
    }

    public function searchMarketStudies(Request $request)
    {
        $term = trim((string) $request->get('q', ''));

        $studies = MarketStudy::query()
            ->where('status', 1)
            ->when($term !== '', function ($query) use ($term) {
                $query->where(function ($q) use ($term) {
                    $q->where('code', 'like', "%{$term}%")
                        ->orWhere('description', 'like', "%{$term}%");
                });
            })
            ->orderByDesc('created_at')
            ->limit(30)
            ->get(['id', 'code', 'description', 'created_at']);

        return response()->json([
            'results' => $studies->map(fn (MarketStudy $study) => [
                'id' => $study->id,
                'text' => trim($study->code . ' | ' . $study->description),
                'code' => $study->code,
                'description' => $study->description,
            ]),
        ]);
    }

    public function searchArticles(Request $request)
    {
        $term = trim((string) $request->get('q', ''));

        $articles = Article::query()
            ->with(['unit:id,description,abbreviation', 'presentation:id,description', 'brand:id,description'])
            ->where('status', 'ACTIVE')
            ->when($term !== '', function ($query) use ($term) {
                $query->where(function ($q) use ($term) {
                    $q->where('code', 'like', "%{$term}%")
                        ->orWhere('legal_name', 'like', "%{$term}%")
                        ->orWhere('commercial_name', 'like', "%{$term}%")
                        ->orWhere('billing_name', 'like', "%{$term}%")
                        ->orWhere('institutional_code', 'like', "%{$term}%");
                });
            })
            ->orderBy('billing_name')
            ->limit(30)
            ->get();

        return response()->json([
            'results' => $articles->map(fn (Article $article) => $this->quoteArticlePayload($article)),
        ]);
    }

    public function quickStoreArticle(Request $request)
    {
        $this->normalizeQuoteArticleNames($request);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:articles,code'],
            'code_type' => ['nullable', 'in:SIGA/SISMED,SAP/IETSI'],
            'institutional_code' => ['nullable', 'string', 'max:100'],
            'legal_name' => ['required', 'string', 'max:255'],
            'commercial_name' => ['nullable', 'string', 'max:255'],
            'billing_name' => ['required', 'string', 'max:255'],
        ], [
            'code.required' => 'El código del artículo es obligatorio.',
            'code.unique' => 'El código del artículo ya existe.',
            'code_type.in' => 'El tipo de código seleccionado no es válido.',
            'institutional_code.max' => 'El código institucional no debe superar 100 caracteres.',
            'legal_name.required' => 'El nombre legal es obligatorio.',
            'billing_name.required' => 'El nombre de facturación es obligatorio.',
        ]);

        $this->validateDuplicateQuoteArticleName($validated);

        $defaultCategory = Category::where('status', 'ACTIVE')->orderBy('id')->first();
        $defaultUnit = Unit::where('status', 'ACTIVE')->orderBy('id')->first();

        if (!$defaultCategory || !$defaultUnit) {
            throw ValidationException::withMessages([
                'article' => 'Debe registrar al menos una categoría y una unidad activas antes de crear artículos rápidos.',
            ]);
        }

        try {
            DB::beginTransaction();

            $article = Article::create([
                'code' => mb_strtoupper($validated['code'], 'UTF-8'),
                'code_type' => $validated['code_type'] ?? 'SIGA/SISMED',
                'institutional_code' => !empty($validated['institutional_code'])
                    ? mb_strtoupper($validated['institutional_code'], 'UTF-8')
                    : null,
                'category_id' => $defaultCategory->id,
                'subcategory_id' => null,
                'presentation_id' => null,
                'unit_id' => $defaultUnit->id,
                'brand_id' => null,
                'legal_name' => $validated['legal_name'],
                'commercial_name' => $validated['commercial_name'] ?: $validated['legal_name'],
                'billing_name' => $validated['billing_name'],
                'is_taxable' => 1,
                'minimum_stock' => 0,
                'has_batch' => 0,
                'has_expiration' => 0,
                'observation' => null,
                'status' => 'ACTIVE',
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ])->fresh(['unit:id,description,abbreviation', 'presentation:id,description', 'brand:id,description']);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Artículo registrado correctamente.',
                'data' => $this->quoteArticlePayload($article),
            ], 201);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Error quick creating quote article: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo registrar el artículo.',
            ], 500);
        }
    }

    public function searchBrands(Request $request)
    {
        $term = trim((string) $request->get('q', ''));

        $brands = Brand::query()
            ->where('status', 'ACTIVE')
            ->when($term !== '', function ($query) use ($term) {
                $query->where(function ($q) use ($term) {
                    $q->where('code', 'like', "%{$term}%")
                        ->orWhere('description', 'like', "%{$term}%");
                });
            })
            ->orderBy('description')
            ->limit(30)
            ->get(['id', 'code', 'description']);

        return response()->json([
            'results' => $brands->map(fn (Brand $brand) => [
                'id' => $brand->id,
                'text' => trim(($brand->code ? $brand->code . ' | ' : '') . $brand->description),
                'description' => $brand->description,
            ]),
        ]);
    }

    public function quickStoreBrand(Request $request)
    {
        $request->merge([
            'description' => mb_strtoupper(trim((string) ($request->input('description') ?: $request->input('name'))), 'UTF-8'),
            'status' => 'ACTIVE',
        ]);

        $validated = $request->validate([
            'description' => [
                'required',
                'string',
                'max:255',
                Rule::unique('brands', 'description')->whereNull('deleted_at'),
            ],
        ], [
            'description.required' => 'El nombre de la marca es obligatorio.',
            'description.unique' => 'Esta marca ya está registrada.',
        ]);

        try {
            DB::beginTransaction();

            $brand = Brand::create([
                'code' => $this->nextQuoteBrandCode(),
                'description' => $validated['description'],
                'observation' => null,
                'status' => 'ACTIVE',
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Marca registrada correctamente.',
                'data' => [
                    'id' => $brand->id,
                    'text' => trim($brand->code . ' | ' . $brand->description),
                    'description' => $brand->description,
                ],
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Error quick creating quote brand: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo registrar la marca.',
            ], 500);
        }
    }

    public function store(Request $request)
    {
        return $this->saveQuote($request);
    }

    public function edit(string $id)
    {
        $quote = Quote::with([
            'customer',
            'company',
            'currency',
            'marketStudy',
            'items.unit',
            'items.presentation',
            'items.brand',
            'items.article',
            'documents' => function ($query) {
                $query->where('observation', 'PDF_GENERATED_QUOTE')
                    ->where('status', 'ACTIVE')
                    ->where('mime_type', 'application/pdf')
                    ->latest('id');
            },
        ])->find($id);

        if (!$quote) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cotización no encontrada.',
            ], 404);
        }

        $pdfDocument = $quote->documents
            ->first(fn (Document $document) => $document->file_path
                && Storage::disk('public')->exists($document->file_path));

        return response()->json([
            'status' => 'success',
            'data' => $quote,
            'pdf_url' => $pdfDocument
                ? Storage::disk('public')->url($pdfDocument->file_path)
                : null,
        ]);
    }

    public function update(Request $request, string $id)
    {
        return $this->saveQuote($request, $id);
    }

    public function destroy(string $id)
    {
        try {
            DB::beginTransaction();

            $quote = Quote::find($id);

            if (!$quote) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'La cotización no existe.'
                ], 404);
            }

            $quote->items()->delete();
            $quote->delete();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Cotización eliminada correctamente.'
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Error deleting quote: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo eliminar la cotización.'
            ], 500);
        }
    }

    private function saveQuote(Request $request, ?string $quoteId = null)
    {
        $quote = $quoteId ? Quote::find($quoteId) : null;

        if ($quoteId && !$quote) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cotización no encontrada.'
            ], 404);
        }

        if (!$quoteId && $this->quoteNumberExists($request->input('quote_number'))) {
            $request->merge([
                'quote_number' => $this->nextQuoteNumber(),
            ]);
        }

        $validated = $request->validate([
            'quote_number' => [
                'required',
                'string',
                'max:255',
                Rule::unique('quotes', 'quote_number')->ignore($quote?->id),
            ],
            'market_study_id' => ['nullable', 'exists:market_studies,id'],
            'customer_id' => ['required', 'exists:customers,id'],
            'customer_branch_id' => ['nullable', 'exists:customer_branches,id'],
            'company_id' => ['required', 'exists:companies,id'],
            'currency_id' => ['required', 'exists:currencies,id'],
            'payment_condition' => ['nullable', 'string', 'max:255'],
            'delivery_address' => ['nullable', 'string'],
            'show_code_type' => ['required', Rule::in(['internal', 'customer', 'both'])],
            'orientation' => ['required', Rule::in(['vertical', 'horizontal'])],
            'billing_type' => ['required', Rule::in(['local', 'export'])],
            'affect_igv' => ['required', 'boolean'],
            'validity_date' => ['nullable', 'date'],
            'delivery_days' => ['nullable', 'integer', 'min:0'],
            'delivery_time' => ['nullable', 'string', 'max:255'],
            'observations' => ['nullable', 'string'],
            'status' => [
                'nullable',
                Rule::in([
                    Quote::STATUS_DRAFT,
                    Quote::STATUS_SENT,
                    Quote::STATUS_APPROVED,
                    Quote::STATUS_REJECTED,
                    Quote::STATUS_EXPIRED,
                    Quote::STATUS_AWARDED,
                ]),
            ],
            'items' => ['required', 'array', 'min:1'],
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
            'items.*.cost_type' => ['nullable', 'string', 'max:255'],
            'items.*.cost_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_percentage' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.line_total' => ['nullable', 'numeric', 'min:0'],
            'items.*.is_winner' => ['nullable', 'boolean'],
        ], [
            'customer_id.required' => 'Debe seleccionar un cliente.',
            'company_id.required' => 'Debe seleccionar una empresa.',
            'currency_id.required' => 'Debe seleccionar una moneda.',
            'items.required' => 'Debe agregar al menos un artículo a la cotización.',
            'items.min' => 'Debe agregar al menos un artículo a la cotización.',
            'items.*.article_id.required' => 'Debe seleccionar un artículo.',
            'items.*.billing_name_snapshot.required' => 'Debe seleccionar un artículo.',
            'items.*.quantity.required' => 'Debe ingresar la cantidad.',
            'items.*.quantity.min' => 'La cantidad debe ser mayor a cero.',
        ]);

        $generatedPdfPath = null;
        $generatedPdfUrl = null;
        $generatedDocumentId = null;
        $pdfError = null;

        try {
            DB::beginTransaction();

            $totals = $this->calculateTotals(
                $validated['items'],
                (bool) $validated['affect_igv']
            );

            $quoteData = [
                'quote_number' => $validated['quote_number'],
                'market_study_id' => $validated['market_study_id'] ?? null,
                'customer_id' => $validated['customer_id'],
                'company_id' => $validated['company_id'],
                'currency_id' => $validated['currency_id'],
                'payment_condition' => $this->upperOrNull($validated['payment_condition'] ?? null),
                'delivery_address' => $this->upperOrNull($validated['delivery_address'] ?? null),
                'show_code_type' => $validated['show_code_type'],
                'orientation' => $validated['orientation'],
                'billing_type' => $validated['billing_type'],
                'affect_igv' => (bool) $validated['affect_igv'],
                'validity_date' => $validated['validity_date'] ?? null,
                'delivery_days' => $validated['delivery_days'] ?? null,
                'delivery_time' => $this->upperOrNull($validated['delivery_time'] ?? null),
                'observations' => $this->upperOrNull($validated['observations'] ?? null),
                'subtotal_exonerated' => $totals['subtotal_exonerated'],
                'subtotal_taxed' => $totals['subtotal_taxed'],
                'igv' => $totals['igv'],
                'grand_total' => $totals['grand_total'],
                'status' => $quote
                    ? ($validated['status'] ?? $quote->status)
                    : Quote::STATUS_SENT,
                'updated_by' => Auth::id(),
            ];

            if ($quote) {
                $quote->update($quoteData);
                $quote->items()->delete();
            } else {
                $quoteData['created_by'] = Auth::id();
                $quote = Quote::create($quoteData);
            }

            foreach ($validated['items'] as $item) {
                $quantity = (float) ($item['quantity'] ?? 0);
                $unitPrice = (float) ($item['unit_price'] ?? 0);
                $discountPercentage = (float) ($item['discount_percentage'] ?? 0);
                $gross = round($quantity * $unitPrice, 2);
                $discountAmount = round($gross * ($discountPercentage / 100), 2);
                $lineTotal = round(max($gross - $discountAmount, 0), 2);

                QuoteItem::create([
                    'quote_id' => $quote->id,
                    'market_study_item_id' => $item['market_study_item_id'] ?? null,
                    'article_id' => $item['article_id'],
                    'article_code' => $this->upperOrNull($item['article_code'] ?? null),
                    'billing_name_snapshot' => $this->upperOrNull($item['billing_name_snapshot'] ?? null),
                    'note' => $this->upperOrNull($item['note'] ?? null),
                    'unit_id' => $item['unit_id'] ?? null,
                    'presentation_id' => $item['presentation_id'] ?? null,
                    'brand_id' => $item['brand_id'] ?? null,
                    'origin' => $this->upperOrNull($item['origin'] ?? null),
                    'expiration_date' => $item['expiration_date'] ?? null,
                    'cost_type' => $this->upperOrNull($item['cost_type'] ?? 'PESO') ?? 'PESO',
                    'cost_price' => (float) ($item['cost_price'] ?? 0),
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount_percentage' => $discountPercentage,
                    'discount_amount' => $discountAmount,
                    'line_total' => $lineTotal,
                    'is_winner' => (bool) ($item['is_winner'] ?? false),
                ]);
            }

            try {
                $pdfData = $this->generateQuotePdf($quote->fresh([
                    'customer',
                    'company',
                    'currency',
                    'items.unit',
                    'items.presentation',
                    'items.brand',
                ]));

                $generatedPdfPath = $pdfData['path'];
                $generatedPdfUrl = $pdfData['url'];
                $generatedDocumentId = $pdfData['document']->id;
            } catch (\Throwable $pdfException) {
                $pdfError = 'La cotización se guardó, pero no se pudo generar el PDF.';

                Log::error('Error generating quote PDF: ' . $pdfException->getMessage());
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => $quoteId
                    ? 'Cotización actualizada correctamente.'
                    : 'Cotización registrada correctamente.',
                'data' => $quote->fresh(['items']),
                'pdf_path' => $generatedPdfPath,
                'pdf_url' => $generatedPdfUrl,
                'document_id' => $generatedDocumentId,
                'pdf_error' => $pdfError,
            ], $quoteId ? 200 : 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            if ($generatedPdfPath && Storage::disk('public')->exists($generatedPdfPath)) {
                Storage::disk('public')->delete($generatedPdfPath);
            }

            Log::error('Error saving quote: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error al guardar la cotización.',
            ], 500);
        }
    }

    private function nextQuoteNumber(): string
    {
        $prefix = 'COT-';

        $lastNumber = Quote::withTrashed()
            ->where('quote_number', 'like', $prefix . '%')
            ->pluck('quote_number')
            ->map(function (?string $quoteNumber) use ($prefix) {
                if (!preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/', (string) $quoteNumber, $matches)) {
                    return 0;
                }

                return (int) $matches[1];
            })
            ->max() ?? 0;

        do {
            $lastNumber++;
            $quoteNumber = $prefix . str_pad($lastNumber, 6, '0', STR_PAD_LEFT);
        } while ($this->quoteNumberExists($quoteNumber));

        return $quoteNumber;
    }

    private function nextQuoteArticleCode(): string
    {
        $nextNumber = (Article::withTrashed()->max('id') ?? 0) + 1;

        do {
            $code = 'ART' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
            $nextNumber++;
        } while (Article::withTrashed()->where('code', $code)->exists());

        return $code;
    }

    private function nextQuoteBrandCode(): string
    {
        $nextNumber = (Brand::withTrashed()->max('id') ?? 0) + 1;

        do {
            $code = 'BRA' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
            $nextNumber++;
        } while (Brand::withTrashed()->where('code', $code)->exists());

        return $code;
    }

    public function generateArticleCode()
    {
        return response()->json([
            'code' => $this->nextQuoteArticleCode(),
        ]);
    }

    private function quoteNumberExists(?string $quoteNumber): bool
    {
        $quoteNumber = trim((string) $quoteNumber);

        if ($quoteNumber === '') {
            return true;
        }

        return Quote::withTrashed()
            ->where('quote_number', $quoteNumber)
            ->exists();
    }

    private function calculateTotals(array $items, bool $affectIgv): array
    {
        $subtotal = 0;

        foreach ($items as $item) {
            $quantity = (float) ($item['quantity'] ?? 0);
            $unitPrice = (float) ($item['unit_price'] ?? 0);
            $discountPercentage = (float) ($item['discount_percentage'] ?? 0);

            $gross = round($quantity * $unitPrice, 2);
            $discountAmount = round($gross * ($discountPercentage / 100), 2);

            $subtotal += round(max($gross - $discountAmount, 0), 2);
        }

        if ($affectIgv) {
            $subtotalTaxed = round($subtotal, 2);
            $igv = round($subtotalTaxed * 0.18, 2);

            return [
                'subtotal_exonerated' => 0,
                'subtotal_taxed' => $subtotalTaxed,
                'igv' => $igv,
                'grand_total' => round($subtotalTaxed + $igv, 2),
            ];
        }

        return [
            'subtotal_exonerated' => round($subtotal, 2),
            'subtotal_taxed' => 0,
            'igv' => 0,
            'grand_total' => round($subtotal, 2),
        ];
    }

    private function upperOrNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== ''
            ? mb_strtoupper($value)
            : null;
    }

    private function quoteArticlePayload(Article $article): array
    {
        $article->loadMissing(['unit:id,description,abbreviation', 'presentation:id,description', 'brand:id,description']);
        $institutionalCode = trim((string) $article->institutional_code);
        $institutionalLabel = $institutionalCode !== ''
            ? trim(($article->code_type ?: 'C.I.') . ': ' . $institutionalCode)
            : null;

        return [
            'id' => $article->id,
            'text' => implode(' | ', array_filter([
                $article->code,
                $institutionalLabel,
                $article->billing_name,
            ])),
            'code' => $article->code,
            'code_type' => $article->code_type,
            'institutional_code' => $article->institutional_code,
            'legal_name' => $article->legal_name,
            'commercial_name' => $article->commercial_name,
            'billing_name' => $article->billing_name,
            'unit_id' => $article->unit_id,
            'unit_text' => $article->unit
                ? trim(($article->unit->abbreviation ? $article->unit->abbreviation . ' | ' : '') . $article->unit->description)
                : '',
            'presentation_id' => $article->presentation_id,
            'presentation_text' => $article->presentation?->description ?? '',
            'brand_id' => $article->brand_id,
            'brand_text' => $article->brand?->description ?? '',
            'origin' => '',
            'cost_price' => 0,
            'is_taxable' => (bool) $article->is_taxable,
        ];
    }

    private function quoteCustomerText(Customer $customer): string
    {
        $document = $customer->ruc ?: $customer->document_number;
        $name = $customer->business_name
            ?: $customer->full_name
            ?: trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? ''));

        return trim(($document ? $document . ' | ' : '') . $name);
    }

    private function mainBranchForCustomer(Customer $customer): ?CustomerBranch
    {
        return $customer->branches()
            ->orderByDesc('is_main')
            ->orderBy('branch_name')
            ->first();
    }

    private function quoteBranchPayload(CustomerBranch $branch): array
    {
        return [
            'id' => $branch->id,
            'text' => $branch->branch_name,
            'branch_name' => $branch->branch_name,
            'address' => $branch->address,
            'is_main' => (bool) $branch->is_main,
        ];
    }

    private function normalizeQuoteArticleNames(Request $request): void
    {
        $legalName = $this->normalizeQuoteArticleText($request->input('legal_name'));
        $commercialName = $this->normalizeQuoteArticleText($request->input('commercial_name')) ?: $legalName;
        $billingName = $this->normalizeQuoteArticleText($request->input('billing_name')) ?: $commercialName;

        $request->merge([
            'legal_name' => $legalName,
            'commercial_name' => $commercialName,
            'billing_name' => $billingName,
        ]);
    }

    private function normalizeQuoteArticleText($value): string
    {
        return mb_strtoupper(trim((string) $value), 'UTF-8');
    }

    private function validateDuplicateQuoteArticleName(array $validated): void
    {
        $names = collect([
            $validated['legal_name'] ?? '',
            $validated['commercial_name'] ?? '',
            $validated['billing_name'] ?? '',
        ])->filter()->unique()->values();

        if ($names->isEmpty()) {
            return;
        }

        $duplicate = Article::query()
            ->where('status', 'ACTIVE')
            ->where(function ($query) use ($names) {
                foreach ($names as $name) {
                    $query->orWhereRaw('UPPER(TRIM(legal_name)) = ?', [$name])
                        ->orWhereRaw('UPPER(TRIM(commercial_name)) = ?', [$name])
                        ->orWhereRaw('UPPER(TRIM(billing_name)) = ?', [$name]);
                }
            })
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'billing_name' => 'Ya existe un artículo registrado con ese nombre.',
            ]);
        }
    }

    private function generateQuotePdf(Quote $quote): array
    {
        $orientation = $quote->orientation === 'horizontal'
            ? 'landscape'
            : 'portrait';

        $fileName = 'cotizacion_' . $this->sanitizeFileName($quote->quote_number) . '.pdf';
        $storedPath = 'quotes/' . $fileName;

        $pdf = Pdf::loadView('admin.quotes.pdf.quote', [
            'quote' => $quote,
            'orientation' => $orientation,
            'logoUrl' => $this->quoteLogoUrl(),
        ])
            ->setPaper('a4', $orientation)
            ->setOption(['isRemoteEnabled' => true]);

        Storage::disk('public')->put($storedPath, $pdf->output());

        $this->deletePreviousGeneratedQuotePdfs($quote, $storedPath);

        $document = Document::create([
            'documentable_type' => Quote::class,
            'documentable_id' => $quote->id,
            'document_type_id' => null,
            'original_name' => $fileName,
            'stored_name' => $fileName,
            'file_path' => $storedPath,
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'file_size' => Storage::disk('public')->size($storedPath) ?: 0,
            'issue_date' => now()->toDateString(),
            'expiration_date' => $quote->validity_date,
            'observation' => 'PDF_GENERATED_QUOTE',
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

    private function deletePreviousGeneratedQuotePdfs(Quote $quote, string $currentPath): void
    {
        $quote->documents()
            ->where('observation', 'PDF_GENERATED_QUOTE')
            ->get()
            ->each(function (Document $document) {
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

    private function quoteLogoUrl(): ?string
    {
        $logoPath = public_path('vendor/adminlte/dist/img/logo_img.png');

        return file_exists($logoPath)
            ? url('vendor/adminlte/dist/img/logo_img.png')
            : null;
    }
}
