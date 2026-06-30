<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\MarketStudy;
use App\Models\MarketStudyItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Yajra\DataTables\Facades\DataTables;


class MarketStudyController extends Controller
{
    /**
     * =========================================================
     * INDEX
     * =========================================================
     */
    public function index()
    {
        return view('admin.market-studies.index');
    }

    /**
     * =========================================================
     * LIST DATATABLE
     * =========================================================
     */
    public function list()
    {
        $marketStudies = MarketStudy::with([
            'creator',
            'updater'
        ])
            ->orderByDesc('id');

        return DataTables::eloquent($marketStudies)
            ->addIndexColumn()

            ->editColumn('code', function ($study) {
                return '<span class="fw-semibold">' . e($study->code) . '</span>';
            })

            ->editColumn('description', function ($study) {
                return e($study->description);
            })

            ->editColumn('reference_terms', function ($study) {
                return $study->reference_terms
                    ? e(\Illuminate\Support\Str::limit($study->reference_terms, 80))
                    : '—';
            })

            ->editColumn('status_badge', function ($study) {
                return $study->status
                    ? '<span class="badge bg-success text-light rounded-pill px-3 py-2 shadow-sm">ACTIVO</span>'
                    : '<span class="badge bg-secondary text-light rounded-pill px-3 py-2 shadow-sm">INACTIVO</span>';
            })
            ->addColumn('acciones', function ($marketStudy) {

                return '
<div class="btn-group shadow-sm" role="group">

    <button type="button"
        class="btn btn-outline-info btn-sm viewMarketStudy mr-2"
        title="Ver Estudio"
        data-id="' . $marketStudy->id . '">

        <i class="fas fa-eye"></i>
    </button>

    <button type="button"
        class="btn btn-outline-primary btn-sm editMarketStudy mr-2"
        title="Editar Estudio"
        data-id="' . $marketStudy->id . '"
        data-code="' . e($marketStudy->code) . '"
        data-description="' . e($marketStudy->description) . '"
        data-reference_terms="' . e($marketStudy->reference_terms) . '"
        data-status="' . ($marketStudy->status ? 1 : 0) . '">

        <i class="fas fa-pen"></i>
    </button>

    <button type="button"
        class="btn btn-outline-warning btn-sm manageQuotes mr-2"
        title="Gestionar Cotizaciones"
        data-id="' . $marketStudy->id . '"
        data-code="' . e($marketStudy->code) . '"
        data-description="' . e($marketStudy->description) . '">

        <i class="fas fa-file-invoice-dollar"></i>
    </button>

    <button type="button"
        class="btn btn-outline-success btn-sm compareQuotes mr-2"
        title="Comparativo de Cotizaciones"
        data-id="' . $marketStudy->id . '"
        data-code="' . e($marketStudy->code) . '"
        data-description="' . e($marketStudy->description) . '">

        <i class="fas fa-balance-scale"></i>
    </button>

    <button type="button"
        class="btn btn-outline-danger btn-sm deleteMarketStudy"
        title="Eliminar Estudio"
        data-id="' . $marketStudy->id . '">

        <i class="fas fa-trash"></i>
    </button>

</div>
';
            })

            ->rawColumns([
                'code',
                'status_badge',
                'acciones'
            ])

            ->make(true);
    }

    /**
     * =========================================================
     * STORE
     * =========================================================
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => [
                'required',
                'unique:market_studies,code'
            ],
            'description' => [
                'required',
                'max:255'
            ],
            'reference_terms' => [
                'nullable',
                'string'
            ],
            'status' => [
                'required',
                'in:0,1'
            ],
            'articles_data' => [
                'nullable'
            ],
            'documents.*' => [
                'nullable',
                'file',
                'max:10240'
            ],
        ]);

        try {
            DB::beginTransaction();

            $validated['code'] = mb_strtoupper($validated['code']);
            $validated['description'] = mb_strtoupper($validated['description']);
            $validated['reference_terms'] = $request->filled('reference_terms')
                ? mb_strtoupper($request->reference_terms)
                : null;

            $validated['status'] = (int) $validated['status'] === 1 ? 1 : 0;
            $validated['created_by'] = Auth::id();
            $validated['updated_by'] = Auth::id();

            $marketStudy = MarketStudy::create($validated);

            /*
            |--------------------------------------------------------------------------
            | ARTÍCULOS DEL ESTUDIO
            |--------------------------------------------------------------------------
            */
            $articlesData = json_decode($request->articles_data, true);

            if (!empty($articlesData) && is_array($articlesData)) {
                foreach ($articlesData as $articleRow) {
                    if (empty($articleRow['article_id'])) {
                        continue;
                    }

                    MarketStudyItem::create([
                        'market_study_id' => $marketStudy->id,
                        'article_id' => $articleRow['article_id'],

                        'article_code_snapshot' => mb_strtoupper($articleRow['article_code_snapshot'] ?? ''),
                        'billing_name_snapshot' => mb_strtoupper($articleRow['billing_name_snapshot'] ?? ''),

                        'category_snapshot' => mb_strtoupper($articleRow['category_snapshot'] ?? ''),
                        'subcategory_snapshot' => mb_strtoupper($articleRow['subcategory_snapshot'] ?? ''),

                        'presentation_snapshot' => mb_strtoupper($articleRow['presentation_snapshot'] ?? ''),
                        'weight_snapshot' => $articleRow['weight_snapshot'] ?? null,
                        'cost_condition_snapshot' => mb_strtoupper($articleRow['cost_condition_snapshot'] ?? ''),

                        'status' => isset($articleRow['status']) && (int) $articleRow['status'] === 1 ? 1 : 0,
                        'created_by' => Auth::id(),
                        'updated_by' => Auth::id(),
                    ]);
                }
            }

            /*
            |--------------------------------------------------------------------------
            | DOCUMENTOS
            |--------------------------------------------------------------------------
            | Nota: aquí se guarda el archivo sin tipo de documento.
            | Si tu tabla documents exige document_type_id NOT NULL,
            | cambia eso a nullable o agrega el selector en el modal.
            |--------------------------------------------------------------------------
            */
            if ($request->hasFile('documents')) {
                foreach ($request->file('documents') as $file) {
                    $storedPath = $file->store('market-studies/documents', 'public');

                    Document::create([
                        'documentable_type' => MarketStudy::class,
                        'documentable_id' => $marketStudy->id,
                        'document_type_id' => null,
                        'original_name' => $file->getClientOriginalName(),
                        'stored_name' => basename($storedPath),
                        'file_path' => $storedPath,
                        'mime_type' => $file->getMimeType(),
                        'extension' => $file->getClientOriginalExtension(),
                        'file_size' => $file->getSize(),
                        'issue_date' => null,
                        'expiration_date' => null,
                        'observation' => null,
                        'status' => 'ACTIVE',
                        'created_by' => Auth::id(),
                        'updated_by' => Auth::id(),
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Estudio de mercado registrado correctamente.',
                'data' => $marketStudy
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Error creando estudio de mercado: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error al registrar el estudio de mercado.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * =========================================================
     * EDIT
     * =========================================================
     */
    public function edit($id)
    {
        $marketStudy = MarketStudy::with([
            'items.article.category',
            'items.article.subcategory',
            'items.article.presentation',
            'documents'
        ])->find($id);

        if (!$marketStudy) {
            return response()->json([
                'status' => 'error',
                'message' => 'Estudio de mercado no encontrado.'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $marketStudy
        ]);
    }

    /**
     * =========================================================
     * UPDATE
     * =========================================================
     */
    public function update(Request $request, $id)
    {
        $marketStudy = MarketStudy::find($id);

        if (!$marketStudy) {
            return response()->json([
                'status' => 'error',
                'message' => 'Estudio de mercado no encontrado.'
            ], 404);
        }

        $validated = $request->validate([
            'code' => [
                'required',
                'unique:market_studies,code,' . $marketStudy->id
            ],
            'description' => [
                'required',
                'max:255'
            ],
            'reference_terms' => [
                'nullable',
                'string'
            ],
            'status' => [
                'required',
                'in:0,1'
            ],
            'articles_data' => [
                'nullable'
            ],
            'documents.*' => [
                'nullable',
                'file',
                'max:10240'
            ],
            'deleted_documents' => [
                'nullable'
            ],
        ]);

        try {
            DB::beginTransaction();

            $validated['code'] = mb_strtoupper($validated['code']);
            $validated['description'] = mb_strtoupper($validated['description']);
            $validated['reference_terms'] = $request->filled('reference_terms')
                ? mb_strtoupper($request->reference_terms)
                : null;

            $validated['status'] = (int) $validated['status'] === 1 ? 1 : 0;
            $validated['updated_by'] = Auth::id();

            $marketStudy->update($validated);

            /*
            |--------------------------------------------------------------------------
            | ELIMINAR DOCUMENTOS
            |--------------------------------------------------------------------------
            */
            $deletedDocuments = json_decode($request->deleted_documents, true);

            if (!empty($deletedDocuments) && is_array($deletedDocuments)) {
                foreach ($deletedDocuments as $documentId) {
                    $document = Document::find($documentId);

                    if ($document) {
                        if (Storage::disk('public')->exists($document->file_path)) {
                            Storage::disk('public')->delete($document->file_path);
                        }

                        $document->delete();
                    }
                }
            }

            /*
            |--------------------------------------------------------------------------
            | REEMPLAZAR ARTÍCULOS
            |--------------------------------------------------------------------------
            */
            MarketStudyItem::where('market_study_id', $marketStudy->id)->delete();

            $articlesData = json_decode($request->articles_data, true);

            if (!empty($articlesData) && is_array($articlesData)) {
                foreach ($articlesData as $articleRow) {
                    if (empty($articleRow['article_id'])) {
                        continue;
                    }

                    MarketStudyItem::create([
                        'market_study_id' => $marketStudy->id,
                        'article_id' => $articleRow['article_id'],

                        'article_code_snapshot' => mb_strtoupper($articleRow['article_code_snapshot'] ?? ''),
                        'billing_name_snapshot' => mb_strtoupper($articleRow['billing_name_snapshot'] ?? ''),

                        'category_snapshot' => mb_strtoupper($articleRow['category_snapshot'] ?? ''),
                        'subcategory_snapshot' => mb_strtoupper($articleRow['subcategory_snapshot'] ?? ''),

                        'presentation_snapshot' => mb_strtoupper($articleRow['presentation_snapshot'] ?? ''),
                        'weight_snapshot' => $articleRow['weight_snapshot'] ?? null,
                        'cost_condition_snapshot' => mb_strtoupper($articleRow['cost_condition_snapshot'] ?? ''),

                        'status' => isset($articleRow['status']) && (int) $articleRow['status'] === 1 ? 1 : 0,
                        'created_by' => Auth::id(),
                        'updated_by' => Auth::id(),
                    ]);
                }
            }

            /*
            |--------------------------------------------------------------------------
            | NUEVOS DOCUMENTOS
            |--------------------------------------------------------------------------
            */
            if ($request->hasFile('documents')) {
                foreach ($request->file('documents') as $file) {
                    $storedPath = $file->store('market-studies/documents', 'public');

                    Document::create([
                        'documentable_type' => MarketStudy::class,
                        'documentable_id' => $marketStudy->id,
                        'document_type_id' => null,
                        'original_name' => $file->getClientOriginalName(),
                        'stored_name' => basename($storedPath),
                        'file_path' => $storedPath,
                        'mime_type' => $file->getMimeType(),
                        'extension' => $file->getClientOriginalExtension(),
                        'file_size' => $file->getSize(),
                        'issue_date' => null,
                        'expiration_date' => null,
                        'observation' => null,
                        'status' => 'ACTIVE',
                        'created_by' => Auth::id(),
                        'updated_by' => Auth::id(),
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Estudio de mercado actualizado correctamente.'
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Error actualizando estudio de mercado: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar el estudio de mercado.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * =========================================================
     * DELETE
     * =========================================================
     */
    public function destroy(MarketStudy $marketStudy)
    {
        DB::beginTransaction();

        try {
            /*
            |--------------------------------------------------------------------------
            | ELIMINAR DOCUMENTOS FISICOS Y REGISTROS
            |--------------------------------------------------------------------------
            */
            foreach ($marketStudy->documents as $document) {
                if ($document->file_path && Storage::disk('public')->exists($document->file_path)) {
                    Storage::disk('public')->delete($document->file_path);
                }

                $document->delete();
            }

            /*
            |--------------------------------------------------------------------------
            | ELIMINAR ITEMS
            |--------------------------------------------------------------------------
            */
            MarketStudyItem::where('market_study_id', $marketStudy->id)->delete();

            $marketStudy->updated_by = Auth::id();
            $marketStudy->save();

            $marketStudy->delete();

            DB::commit();

            return response()->json([
                'message' => 'Estudio de mercado eliminado correctamente.'
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Error eliminando estudio de mercado: ' . $e->getMessage());

            return response()->json([
                'message' => 'Error al eliminar el estudio de mercado.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * =========================================================
     * GENERATE CODE
     * =========================================================
     */
    public function generateCode()
    {
        do {

            $code = 'EM-' . str_pad(
                random_int(1, 99999),
                5,
                '0',
                STR_PAD_LEFT
            );
        } while (
            MarketStudy::where('code', $code)->exists()
        );

        return response()->json([
            'code' => $code
        ]);
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

            'winners.quoteItem',
            'winners.quoteItem.brand',
            'winners.quoteItem.unit',
            'winners.quoteItem.presentation',
            'winners.quoteItem.quote.supplier',

            'documents'

        ]);

        $marketStudy->setAttribute(
            'economic_summary',
            $this->calculateWinnerEconomicSummary($marketStudy)
        );

        return response()->json([
            'success' => true,
            'data' => $marketStudy
        ]);
    }

    private function calculateWinnerEconomicSummary(MarketStudy $marketStudy): array
    {
        $gravada = 0;
        $exonerada = 0;
        $inafecta = 0;
        $igv = 0;
        $total = 0;

        foreach ($marketStudy->winners as $winner) {
            $quoteItem = $winner->quoteItem;

            if (!$quoteItem) {
                continue;
            }

            $quantity = (float) ($quoteItem->quantity ?? 0);
            $unitPrice = (float) ($quoteItem->unit_price ?? 0);
            $lineTotal = round($quantity * $unitPrice, 2);

            if ($lineTotal <= 0) {
                $lineTotal = round((float) ($quoteItem->total ?? 0), 2);
            }

            $taxType = strtoupper($quoteItem->tax_type ?? 'GRAVADA');

            if ($taxType === 'GRAVADA') {
                $tax = round($lineTotal * 0.18, 2);

                $gravada += $lineTotal;
                $igv += $tax;
                $total += $lineTotal + $tax;
            } elseif ($taxType === 'EXONERADA') {
                $exonerada += $lineTotal;
                $total += $lineTotal;
            } else {
                $inafecta += $lineTotal;
                $total += $lineTotal;
            }
        }

        return [
            'gravada' => round($gravada, 2),
            'exonerada' => round($exonerada, 2),
            'inafecta' => round($inafecta, 2),
            'igv' => round($igv, 2),
            'total' => round($total, 2),
        ];
    }

    public function winners()
    {
        return $this->hasManyThrough(
            \App\Models\MarketStudyItemWinner::class,
            \App\Models\MarketStudyItem::class,
            'market_study_id',
            'market_study_item_id',
            'id',
            'id'
        );
    }
}
