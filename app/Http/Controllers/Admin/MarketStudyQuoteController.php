<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MarketStudyQuote;
use Illuminate\Http\Request;
use App\Models\Supplier;
use App\Models\Currency;
use Illuminate\Support\Facades\Http;

use App\Models\MarketStudyQuoteItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

use App\Models\MarketStudyItem;

class MarketStudyQuoteController extends Controller
{



    /**
     * ==========================================
     * PROVEEDORES ACTIVOS
     * ==========================================
     */
    public function suppliers()
    {
        $suppliers = Supplier::query()
            ->where('status', 'ACTIVE')
            ->orderBy('business_name')
            ->get([
                'id',
                'business_name'
            ]);

        return response()->json($suppliers);
    }

    /**
     * ==========================================
     * MONEDAS ACTIVAS
     * ==========================================
     */
    public function currencies()
    {
        $currencies = Currency::query()
            ->where('status', 'ACTIVE')
            ->orderBy('description')
            ->get([
                'id',
                'description',
                'symbol'
            ]);

        return response()->json($currencies);
    }
    /*
    *
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        return $this->saveQuote($request, null);
    }

    public function update(Request $request, string $id)
    {
        return $this->saveQuote($request, $id);
    }

    private function saveQuote(Request $request, ?string $quoteId = null)
    {
        $quote = $quoteId
            ? MarketStudyQuote::find($quoteId)
            : null;

        if ($quoteId && !$quote) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cotización no encontrada.'
            ], 404);
        }

        $validated = $request->validate([
            'market_study_id' => ['required', 'exists:market_studies,id'],
            'quote_number' => [
                'required',
                'string',
                'max:20',
                Rule::unique('market_study_quotes', 'quote_number')
                    ->ignore($quote?->id)
            ],
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'currency_id' => ['required', 'exists:currencies,id'],
            'exchange_rate' => ['required', 'numeric', 'min:0'],
            'payment_condition' => ['nullable', 'string', 'max:100'],
            'shipping_cost' => ['nullable', 'numeric', 'min:0'],
            'other_costs' => ['nullable', 'numeric', 'min:0'],
            'delivery_date' => ['nullable', 'date'],
            'commercial_conditions' => ['nullable', 'string'],
            'status' => ['required', 'boolean'],
            'items_data' => ['required', 'json'],
            'gravada' => ['nullable', 'numeric', 'min:0'],
            'exonerada' => ['nullable', 'numeric', 'min:0'],
            'inafecta' => ['nullable', 'numeric', 'min:0'],
            'igv' => ['nullable', 'numeric', 'min:0'],
            'grand_total' => ['nullable', 'numeric', 'min:0'],
        ]);

        $items = json_decode($validated['items_data'], true);

        if (!is_array($items) || count($items) === 0) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Debe agregar al menos un ítem a la cotización antes de guardar.'
            ], 422);
        }

        foreach ($items as $index => $item) {

            if (
                empty($item['brand_id']) ||
                empty($item['unit_id']) ||
                empty($item['presentation_id']) ||
                empty($item['manufacture_date']) ||
                empty($item['expiration_date']) ||
                empty($item['origin']) ||
                empty($item['sanitary_registration'])
            ) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Existen ítems con información incompleta. Debe configurar todos los ítems antes de guardar.'
                ], 422);
            }
        }

        if (!is_array($items) || empty($items)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Debe agregar al menos un ítem a la cotización.'
            ], 422);
        }

        try {
            DB::beginTransaction();

            $totals = $this->calculateQuoteTotals($items);

            $quoteData = [
                'market_study_id' => $validated['market_study_id'],
                'quote_number' => $validated['quote_number'],
                'supplier_id' => $validated['supplier_id'],
                'currency_id' => $validated['currency_id'],
                'exchange_rate' => $validated['exchange_rate'],
                'payment_condition' => $validated['payment_condition'] ?? null,
                'shipping_cost' => $validated['shipping_cost'] ?? 0,
                'other_costs' => $validated['other_costs'] ?? 0,
                'delivery_date' => $validated['delivery_date'] ?? null,
                'commercial_conditions' => $validated['commercial_conditions'] ?? null,
                'gravada' => $totals['gravada'],
                'exonerada' => $totals['exonerada'],
                'inafecta' => $totals['inafecta'],
                'igv' => $totals['igv'],
                'grand_total' => $totals['grand_total'],
                'status' => $validated['status'],
                'updated_by' => Auth::id(),
            ];

            if ($quote) {
                $quote->update($quoteData);

                MarketStudyQuoteItem::where('market_study_quote_id', $quote->id)->delete();
            } else {
                $quoteData['created_by'] = Auth::id();
                $quote = MarketStudyQuote::create($quoteData);
            }

            foreach ($items as $item) {
                $gross = round(((float)($item['quantity'] ?? 0)) * ((float)($item['unit_price'] ?? 0)), 3);
                $taxType = strtoupper($item['tax_type'] ?? 'GRAVADA');

                if ($taxType === 'GRAVADA') {
                    $base = round($gross / 1.18, 3);
                    $taxAmount = round($gross - $base, 3);
                    $subtotal = $base;
                    $total = $gross;
                } else {
                    $taxAmount = 0;
                    $subtotal = $gross;
                    $total = $gross;
                }

                MarketStudyQuoteItem::create([
                    'market_study_quote_id' => $quote->id,
                    'market_study_item_id' => $item['market_study_item_id'] ?? null,
                    'article_id' => $item['article_id'] ?? null,
                    'brand_id' => $item['brand_id'] ?? null,
                    'unit_id' => $item['unit_id'] ?? null,
                    'presentation_id' => $item['presentation_id'] ?? null,
                    'manufacture_date' => !empty($item['manufacture_date'])
                        ? Carbon::parse($item['manufacture_date'])->format('Y-m-d')
                        : null,
                    'expiration_date' => !empty($item['expiration_date'])
                        ? Carbon::parse($item['expiration_date'])->format('Y-m-d')
                        : null,
                    'origin' => !empty($item['origin']) ? mb_strtoupper($item['origin']) : null,
                    'sanitary_registration' => !empty($item['sanitary_registration'])
                        ? mb_strtoupper($item['sanitary_registration'])
                        : null,
                    'tax_type' => $taxType,
                    'quantity' => (float)($item['quantity'] ?? 0),
                    'unit_price' => (float)($item['unit_price'] ?? 0),
                    'subtotal' => $subtotal,
                    'tax_amount' => $taxAmount,
                    'total' => $total,
                    'observation' => !empty($item['observation']) ? mb_strtoupper($item['observation']) : null,
                    'status' => $item['status'] ?? 1,
                    'created_by' => Auth::id(),
                    'updated_by' => Auth::id(),
                ]);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => $quoteId
                    ? 'Cotización actualizada correctamente.'
                    : 'Cotización registrada correctamente.',
                'data' => $quote->fresh()
            ], $quoteId ? 200 : 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Error saving market study quote: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error al guardar la cotización.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function calculateQuoteTotals(array $items): array
    {
        $gravada = 0;
        $exonerada = 0;
        $inafecta = 0;
        $igv = 0;
        $grandTotal = 0;

        foreach ($items as $item) {
            $qty = (float)($item['quantity'] ?? 0);
            $unitPrice = (float)($item['unit_price'] ?? 0);
            $gross = round($qty * $unitPrice, 3);
            $taxType = strtoupper($item['tax_type'] ?? 'GRAVADA');

            if ($taxType === 'GRAVADA') {
                $base = round($gross / 1.18, 3);
                $tax = round($gross - $base, 3);

                $gravada += $base;
                $igv += $tax;
                $grandTotal += $gross;
            } elseif ($taxType === 'EXONERADA') {
                $exonerada += $gross;
                $grandTotal += $gross;
            } else {
                $inafecta += $gross;
                $grandTotal += $gross;
            }
        }

        return [
            'gravada' => round($gravada, 3),
            'exonerada' => round($exonerada, 3),
            'inafecta' => round($inafecta, 3),
            'igv' => round($igv, 3),
            'grand_total' => round($grandTotal, 3),
        ];
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $quote = MarketStudyQuote::with([
            'marketStudy:id,code,description',
            'supplier:id,business_name',
            'currency:id,description,symbol',
            'items.article:id,code,billing_name',
            'items.brand:id,description',
            'items.unit:id,description',
            'items.presentation:id,description'
        ])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $quote
        ]);
    }

    /**
     * Update the specified resource in storage.
     */


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {

            DB::beginTransaction();

            $quote = MarketStudyQuote::find($id);

            if (!$quote) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'La cotización no existe.'
                ], 404);
            }

            // Eliminar primero los detalles
            MarketStudyQuoteItem::where(
                'market_study_quote_id',
                $quote->id
            )->delete();

            // Eliminar cabecera
            $quote->delete();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Cotización eliminada correctamente.'
            ]);
        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error(
                'Error al eliminar cotización: ' .
                    $e->getMessage()
            );

            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo eliminar la cotización.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function generateNumber()
    {
        $last = MarketStudyQuote::withTrashed()
            ->orderByDesc('id')
            ->first();

        $next = $last
            ? $last->id + 1
            : 1;

        return response()->json([
            'quote_number' => 'COT-' . str_pad(
                $next,
                6,
                '0',
                STR_PAD_LEFT
            )
        ]);
    }

    public function exchangeRate()
    {
        try {

            $response = Http::timeout(10)
                ->get(
                    'https://www.sunat.gob.pe/a/txt/tipoCambio.txt'
                );

            if (!$response->successful()) {

                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo obtener el tipo de cambio.'
                ], 500);
            }

            $parts = explode('|', trim($response->body()));

            return response()->json([

                'success' => true,

                'date' => $parts[0] ?? null,

                'buy' => $parts[1] ?? null,

                'sell' => $parts[2] ?? null,

                'exchange_rate' => $parts[2] ?? null // venta

            ]);
        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function supplierDetail(Supplier $supplier)
    {
        return response()->json([
            'id'                => $supplier->id,
            'business_name'     => $supplier->business_name,
            'payment_condition' => $supplier->payment_condition,
        ]);
    }


    public function studyItems($marketStudyId)
    {
        $items = MarketStudyItem::query()
            ->where('market_study_id', $marketStudyId)
            ->get([
                'id',
                'article_id',
                'article_code_snapshot',
                'billing_name_snapshot',
                'category_snapshot',
                'subcategory_snapshot',
                'presentation_snapshot',
                'cost_condition_snapshot'
            ]);

        return response()->json($items);
    }


    public function listByStudy($id)
    {
        $quotes = MarketStudyQuote::with([
            'supplier:id,business_name',
            'currency:id,description,symbol'
        ])
            ->where('market_study_id', $id)
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $quotes
        ]);
    }
}
