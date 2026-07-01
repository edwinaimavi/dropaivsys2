<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MarketStudy;
use App\Models\MarketStudyItemWinner;
use Illuminate\Support\Facades\DB;

class MarketStudyComparisonController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:admin.market-study-comparisons.show')->only(['show']);
        $this->middleware('can:admin.market-study-comparisons.save')->only(['save']);
    }

    public function show(MarketStudy $marketStudy)
    {
        $marketStudy->load([
            'items',
            'quotes.supplier',
            'quotes.currency',
            'quotes.items.article',
            'quotes.items.brand',
            'quotes.items.unit',
            'quotes.items.presentation',
        ]);

        // Cargar los ganadores guardados
        $winners = MarketStudyItemWinner::whereIn(
            'market_study_item_id',
            $marketStudy->items->pluck('id')
        )->get();

        return response()->json([
            'success' => true,
            'data' => $marketStudy,
            'winners' => $winners
        ]);
    }


    public function save(Request $request, MarketStudy $marketStudy)
    {
        DB::beginTransaction();

        try {

            $selections = $request->input('selections', []);

            foreach ($selections as $item) {

                MarketStudyItemWinner::updateOrCreate(

                    [
                        'market_study_item_id' =>
                        $item['market_study_item_id']
                    ],

                    [
                        'market_study_quote_item_id' =>
                        $item['quote_item_id']
                    ]

                );
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Comparativo guardado correctamente.'
            ]);
        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
