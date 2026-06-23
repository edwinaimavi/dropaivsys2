<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Models\Quote;
use App\Models\Customer;
use App\Models\CustomerBranch;
use App\Models\Company;
use App\Models\Currency;
use App\Models\MarketStudy;
use App\Models\Unit;
use App\Models\Presentation;
use App\Models\Brand;

class QuoteController extends Controller
{
    public function index()
    {
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
        $prefix = 'COT-';

        $lastQuote = Quote::query()
            ->where('quote_number', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->first();

        if ($lastQuote && $lastQuote->quote_number) {

            $lastNumber = (int) str_replace($prefix, '', $lastQuote->quote_number);

            $nextNumber = $lastNumber + 1;
        } else {

            $nextNumber = 1;
        }

        do {

            $quoteNumber = $prefix . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);

            $exists = Quote::query()
                ->where('quote_number', $quoteNumber)
                ->exists();

            if ($exists) {
                $nextNumber++;
            }
        } while ($exists);

        return response()->json([
            'quote_number' => $quoteNumber,
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
        //
    }

    public function edit(string $id)
    {
        //
    }

    public function update(Request $request, string $id)
    {
        //
    }

    public function destroy(string $id)
    {
        //
    }
}
