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

class QuoteController extends Controller
{
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
                    ? $quote->created_at->format('d/m/Y H:i')
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

        return response()->json([
            'items' => $items,
        ]);
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
