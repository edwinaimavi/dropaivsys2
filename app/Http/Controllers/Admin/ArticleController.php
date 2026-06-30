<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

use App\Models\Article;
use App\Models\Brand as ModelsBrand;
use Yajra\DataTables\Facades\DataTables;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Models\Category;
use App\Models\Subcategory;
use App\Models\Presentation;
use App\Models\Unit;
use App\Models\DocumentType;

use App\Models\Document;
use Illuminate\Support\Facades\Storage;
use App\Models\Image;

class ArticleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    /**
     * =========================================================
     * INDEX
     * =========================================================
     */
    public function index()
    {
        $categories = Category::where('status', 'ACTIVE')
            ->orderBy('description')
            ->get();

        $brands = ModelsBrand::where('status', 'ACTIVE')
            ->orderBy('description')
            ->get();


        $presentations = Presentation::where('status', 'ACTIVE')
            ->orderBy('description')
            ->get();

        $units = Unit::where('status', 'ACTIVE')
            ->orderBy('description')
            ->get();

        $documentTypes = DocumentType::where(
            'status',
            'ACTIVE'
        )
            ->orderBy('description')
            ->get();

        return view(
            'admin.articles.index',
            compact(
                'categories',
                'brands',
                'presentations',
                'units',
                'documentTypes',
            )
        );
    }

    /**
     * =========================================================
     * LIST DATATABLE
     * =========================================================
     */
    public function list()
    {
        $articles = Article::with([
            'category',
            'subcategory',
            'brand',
            'creator',
            'editor'
        ])
            ->orderBy('id', 'desc')
            ->get();

        return DataTables::of($articles)

            ->addIndexColumn()

            ->addColumn('brand', function ($article) {

                return $article->brand->description ?? '-';
            })

            ->addColumn('legal_name', function ($article) {

                return $article->legal_name;
            })

            ->addColumn('commercial_name', function ($article) {

                return $article->commercial_name;
            })

            ->addColumn('is_taxable', function ($article) {

                return $article->is_taxable
                    ? 'SI'
                    : 'NO';
            })

            ->editColumn('status', function ($article) {

                $colors = [

                    'ACTIVE'   => 'secondary',

                    'INACTIVE' => 'danger'

                ];

                $color = $colors[$article->status] ?? 'secondary';

                $statusText = match ($article->status) {

                    'ACTIVE'   => 'ACTIVO',

                    'INACTIVE' => 'INACTIVO',

                    default    => $article->status
                };

                return '
                <span class="badge bg-' . $color . ' text-light rounded-pill px-3 py-2 shadow-sm">
                    ' . $statusText . '
                </span>
            ';
            })

            ->addColumn('acciones', function ($article) {

                return view(
                    'admin.articles.partials.acciones',
                    compact('article')
                )->render();
            })

            ->rawColumns([
                'status',
                'acciones'
            ])

            ->make(true);
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
    /**
     * =========================================================
     * STORE
     * =========================================================
     */
    public function store(Request $request)
    {
        $this->normalizeArticleNames($request);

        $validated = $request->validate([

            'code' => [
                'required',
                'unique:articles,code'
            ],

            'code_type' => [
                'nullable',
                'in:SIGA/SISMED,SAP/IETSI'
            ],

            'institutional_code' => [
                'nullable',
                'max:100'
            ],

            'minimum_stock' => [
                'nullable',
                'numeric'
            ],

            'is_taxable' => [
                'nullable',
                'boolean'
            ],

            'has_batch' => [
                'nullable',
                'boolean'
            ],

            'has_expiration' => [
                'nullable',
                'boolean'
            ],

            'observation' => [
                'nullable',
                'string'
            ],

            'category_id' => [
                'required'
            ],

            'subcategory_id' => [
                'required'
            ],

            'presentation_id' => [
                'required'
            ],

            'unit_id' => [
                'required'
            ],

            'brand_id' => [
                'required'
            ],

            'legal_name' => [
                'required',
                'max:255'
            ],

            'commercial_name' => [
                'required',
                'max:255'
            ],

            'billing_name' => [
                'required',
                'max:255'
            ],

            'status' => [
                'required',
                'in:ACTIVE,INACTIVE'
            ],
            'documents_data' => [
                'nullable'
            ],
            'images.*' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:4096'
            ],

        ]);

        $this->validateDuplicateArticleName($validated);

        try {

            DB::beginTransaction();

            if ($request->filled('institutional_code')) {

                $validated['institutional_code'] =
                    mb_strtoupper(
                        $request->institutional_code
                    );
            }

            if ($request->filled('observation')) {

                $validated['observation'] =
                    mb_strtoupper(
                        $request->observation
                    );
            }

            $validated['created_by'] = Auth::id();
            $validated['updated_by'] = Auth::id();

            $validated['minimum_stock'] =
                $request->minimum_stock ?? 0;

            $validated['is_taxable'] =
                $request->is_taxable ?? 0;

            $validated['has_batch'] =
                $request->has_batch ?? 0;

            $validated['has_expiration'] =
                $request->has_expiration ?? 0;


            $article = Article::create($validated);

            if ($request->hasFile('images')) {

                foreach (
                    $request->file('images')
                    as $image
                ) {

                    $storedPath =
                        $image->store(
                            'articles/images',
                            'public'
                        );

                    Image::create([

                        'imageable_type' =>
                        Article::class,

                        'imageable_id' =>
                        $article->id,

                        'original_name' =>
                        $image->getClientOriginalName(),

                        'stored_name' =>
                        basename($storedPath),

                        'file_path' =>
                        $storedPath,

                        'mime_type' =>
                        $image->getMimeType(),

                        'extension' =>
                        $image->getClientOriginalExtension(),

                        'file_size' =>
                        $image->getSize(),

                        'sort_order' =>
                        0,

                        'status' =>
                        'ACTIVE',

                        'created_by' =>
                        Auth::id(),

                        'updated_by' =>
                        Auth::id()

                    ]);
                }
            }

            $documentsData = json_decode(
                $request->documents_data,
                true
            );

            if ($documentsData) {

                foreach (
                    $documentsData as $index => $doc
                ) {

                    if (
                        $request->hasFile(
                            "documents_files.$index"
                        )
                    ) {

                        $file =
                            $request->file(
                                "documents_files.$index"
                            );

                        $storedPath =
                            $file->store(
                                'articles',
                                'public'
                            );

                        Document::create([

                            'documentable_type' =>
                            Article::class,

                            'documentable_id' =>
                            $article->id,

                            'document_type_id' =>
                            $doc['document_type_id'],

                            'original_name' =>
                            $file->getClientOriginalName(),

                            'stored_name' =>
                            basename($storedPath),

                            'file_path' =>
                            $storedPath,

                            'mime_type' =>
                            $file->getMimeType(),

                            'extension' =>
                            $file->getClientOriginalExtension(),

                            'file_size' =>
                            $file->getSize(),

                            'issue_date' =>
                            $doc['issue_date'] ?: null,

                            'expiration_date' =>
                            $doc['expiration_date'] ?: null,

                            'observation' =>
                            $doc['observation'] ?? null,

                            'status' =>
                            'ACTIVE',

                            'created_by' =>
                            Auth::id(),

                            'updated_by' =>
                            Auth::id()

                        ]);
                    }
                }
            }

            DB::commit();

            return response()->json([

                'status' => 'success',

                'message' =>
                'Artículo registrado correctamente.',

                'data' => $article

            ], 201);
        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error(
                'Error creating article: ' .
                    $e->getMessage()
            );

            return response()->json([

                'status' => 'error',

                'message' =>
                'Error al registrar el artículo.',

                'error' => $e->getMessage()

            ], 500);
        }
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
    /**
     * =========================================================
     * EDIT
     * =========================================================
     */
    public function edit($id)
    {
        $article = Article::with([

            'images',

            'documents.documentType'

        ])->find($id);

        if (!$article) {

            return response()->json([
                'status' => 'error',
                'message' => 'Artículo no encontrado.'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $article
        ]);
    }
    /**
     * Update the specified resource in storage.
     */
    /**
     * =========================================================
     * UPDATE
     * =========================================================
     */
    public function update(Request $request, $id)
    {
        $article = Article::find($id);

        if (!$article) {

            return response()->json([
                'status' => 'error',
                'message' => 'Artículo no encontrado.'
            ], 404);
        }

        $this->normalizeArticleNames($request);

        $validated = $request->validate([

            'code' => [
                'required',
                'unique:articles,code,' . $article->id
            ],

            'code_type' => [
                'nullable',
                'in:SIGA/SISMED,SAP/IETSI'
            ],

            'institutional_code' => [
                'nullable',
                'max:100'
            ],

            'category_id' => [
                'required'
            ],

            'subcategory_id' => [
                'required'
            ],

            'presentation_id' => [
                'required'
            ],

            'unit_id' => [
                'required'
            ],

            'brand_id' => [
                'required'
            ],

            'legal_name' => [
                'required',
                'max:255'
            ],

            'commercial_name' => [
                'required',
                'max:255'
            ],

            'billing_name' => [
                'required',
                'max:255'
            ],

            'status' => [
                'required',
                'in:ACTIVE,INACTIVE'
            ],

            'images.*' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:4096'
            ]
        ]);

        $this->validateDuplicateArticleName($validated, $article->id);

        try {

            DB::beginTransaction();

            $validated['institutional_code'] =
                $request->institutional_code
                ? mb_strtoupper($request->institutional_code)
                : null;

            $validated['observation'] =
                $request->observation
                ? mb_strtoupper($request->observation)
                : null;

            $validated['updated_by'] =
                Auth::id();

            $article->update($validated);

            /*
        |--------------------------------------------------------------------------
        | ELIMINAR IMAGENES
        |--------------------------------------------------------------------------
        */
            $deletedImages = json_decode(
                $request->deleted_images,
                true
            );

            if ($deletedImages) {

                foreach ($deletedImages as $imageId) {

                    $image = Image::find($imageId);

                    if ($image) {

                        if (
                            Storage::disk('public')
                            ->exists($image->file_path)
                        ) {

                            Storage::disk('public')
                                ->delete($image->file_path);
                        }

                        $image->delete();
                    }
                }
            }

            /*
        |--------------------------------------------------------------------------
        | NUEVAS IMAGENES
        |--------------------------------------------------------------------------
        */
            if ($request->hasFile('images')) {

                foreach (
                    $request->file('images')
                    as $imageFile
                ) {

                    $storedPath =
                        $imageFile->store(
                            'articles/images',
                            'public'
                        );

                    Image::create([

                        'imageable_type' =>
                        Article::class,

                        'imageable_id' =>
                        $article->id,

                        'original_name' =>
                        $imageFile->getClientOriginalName(),

                        'stored_name' =>
                        basename($storedPath),

                        'file_path' =>
                        $storedPath,

                        'mime_type' =>
                        $imageFile->getMimeType(),

                        'extension' =>
                        $imageFile->getClientOriginalExtension(),

                        'file_size' =>
                        $imageFile->getSize(),

                        'sort_order' =>
                        0,

                        'status' =>
                        'ACTIVE',

                        'created_by' =>
                        Auth::id(),

                        'updated_by' =>
                        Auth::id()

                    ]);
                }
            }

            /*
        |--------------------------------------------------------------------------
        | ELIMINAR DOCUMENTOS
        |--------------------------------------------------------------------------
        */
            $deletedDocuments = json_decode(
                $request->deleted_documents,
                true
            );

            if ($deletedDocuments) {

                foreach ($deletedDocuments as $documentId) {

                    $document =
                        Document::find($documentId);

                    if ($document) {

                        if (
                            Storage::disk('public')
                            ->exists($document->file_path)
                        ) {

                            Storage::disk('public')
                                ->delete($document->file_path);
                        }

                        $document->delete();
                    }
                }
            }

            /*
        |--------------------------------------------------------------------------
        | NUEVOS DOCUMENTOS
        |--------------------------------------------------------------------------
        */
            $documentsData = json_decode(
                $request->documents_data,
                true
            );

            if ($documentsData) {

                foreach (
                    $documentsData as $index => $doc
                ) {

                    if (
                        $request->hasFile(
                            "documents_files.$index"
                        )
                    ) {

                        $file =
                            $request->file(
                                "documents_files.$index"
                            );

                        $storedPath =
                            $file->store(
                                'articles',
                                'public'
                            );

                        Document::create([

                            'documentable_type' =>
                            Article::class,

                            'documentable_id' =>
                            $article->id,

                            'document_type_id' =>
                            $doc['document_type_id'],

                            'original_name' =>
                            $file->getClientOriginalName(),

                            'stored_name' =>
                            basename($storedPath),

                            'file_path' =>
                            $storedPath,

                            'mime_type' =>
                            $file->getMimeType(),

                            'extension' =>
                            $file->getClientOriginalExtension(),

                            'file_size' =>
                            $file->getSize(),

                            'issue_date' =>
                            $doc['issue_date'] ?? null,

                            'expiration_date' =>
                            $doc['expiration_date'] ?? null,

                            'observation' =>
                            $doc['observation'] ?? null,

                            'status' =>
                            'ACTIVE',

                            'created_by' =>
                            Auth::id(),

                            'updated_by' =>
                            Auth::id()

                        ]);
                    }
                }
            }

            DB::commit();

            return response()->json([

                'status' => 'success',

                'message' =>
                'Artículo actualizado correctamente.'

            ]);
        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([

                'status' => 'error',

                'message' =>
                'Error al actualizar el artículo.',

                'error' => $e->getMessage()

            ], 500);
        }
    }

    private function normalizeArticleNames(Request $request): void
    {
        $request->merge([
            'legal_name' => $this->normalizeArticleText(
                $request->input('legal_name')
            ),
            'commercial_name' => $this->normalizeArticleText(
                $request->input('commercial_name')
            ),
            'billing_name' => $this->normalizeArticleText(
                $request->input('billing_name')
            ),
        ]);
    }

    private function normalizeArticleText($value): string
    {
        return mb_strtoupper(trim((string) $value), 'UTF-8');
    }

    private function validateDuplicateArticleName(
        array $validated,
        ?int $ignoreId = null
    ): void {
        $nameFields = [
            'legal_name',
            'commercial_name',
            'billing_name',
        ];

        $names = collect($nameFields)
            ->mapWithKeys(fn ($field) => [
                $field => $this->normalizeArticleText(
                    $validated[$field] ?? ''
                ),
            ])
            ->filter()
            ->all();

        if (empty($names)) {
            return;
        }

        $query = Article::query()
            ->where('brand_id', $validated['brand_id'])
            ->where('status', 'ACTIVE');

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        $query->where(function ($query) use ($names) {
            foreach ($names as $name) {
                $query->orWhereRaw(
                    'UPPER(TRIM(legal_name)) = ?',
                    [$name]
                )
                    ->orWhereRaw(
                        'UPPER(TRIM(commercial_name)) = ?',
                        [$name]
                    )
                    ->orWhereRaw(
                        'UPPER(TRIM(billing_name)) = ?',
                        [$name]
                    );
            }
        });

        $duplicate = $query->first([
            'legal_name',
            'commercial_name',
            'billing_name',
        ]);

        if (!$duplicate) {
            return;
        }

        $existingNames = collect($nameFields)
            ->map(fn ($field) => $this->normalizeArticleText(
                $duplicate->{$field}
            ))
            ->filter()
            ->unique()
            ->values();

        $field = collect($names)
            ->search(fn ($name) => $existingNames->contains($name));

        throw ValidationException::withMessages([
            $field ?: 'legal_name' => [
                'Ya existe un art\u{00ED}culo registrado con ese nombre para la misma marca.',
            ],
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    /**
     * =========================================================
     * DELETE
     * =========================================================
     */
    public function destroy(Article $article)
    {
        DB::beginTransaction();

        try {

            $article->deleted_by = Auth::id();

            $article->save();

            $article->delete();

            DB::commit();

            return response()->json([

                'message' =>
                'Artículo eliminado correctamente.'

            ]);
        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([

                'message' =>
                'Error al eliminar el artículo.',

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
        $lastArticle = Article::withTrashed()
            ->orderBy('id', 'desc')
            ->first();

        $nextNumber = $lastArticle
            ? $lastArticle->id + 1
            : 1;

        return response()->json([

            'code' =>
            'ART' . str_pad(
                $nextNumber,
                5,
                '0',
                STR_PAD_LEFT
            )

        ]);
    }

    public function getSubcategories($categoryId)
    {
        $subcategories = Subcategory::where(
            'category_id',
            $categoryId
        )
            ->where('status', 'ACTIVE')
            ->orderBy('description')
            ->get();

        return response()->json($subcategories);
    }


    public function showData($id)
    {
        $article = Article::with([

            'category',
            'subcategory',
            'brand',
            'presentation',
            'unit',

            'creator',
            'editor',

            'documents.documentType',

            'images'

        ])->find($id);

        if (!$article) {

            return response()->json([

                'status' => 'error',

                'message' => 'Artículo no encontrado'

            ], 404);
        }

        return response()->json([

            'status' => 'success',

            'data' => $article

        ]);
    }


    public function listPicker()
    {
        $articles = Article::with([
            'category',
            'subcategory',
            'presentation',
            'unit',
            'brand'
        ])
            ->where('status', 1)
            ->orderBy('billing_name');

        return DataTables::eloquent($articles)

            ->addColumn('category_name', function ($article) {
                return $article->category?->description;
            })

            ->addColumn('subcategory_name', function ($article) {
                return $article->subcategory?->description;
            })

            ->addColumn('presentation_name', function ($article) {
                return $article->presentation?->description;
            })

            ->addColumn('unit_name', function ($article) {
                return $article->unit?->description;
            })

            ->addColumn('brand_name', function ($article) {
                return $article->brand?->description;
            })

            ->addColumn('action', function ($article) {

                return '
                <button
                    type="button"
                    class="btn btn-success btn-sm selectArticle"

                    data-id="' . $article->id . '"
                    data-code="' . e($article->code) . '"
                    data-name="' . e($article->billing_name) . '"

                    data-category="' . e(optional($article->category)->description) . '"
                    data-subcategory="' . e(optional($article->subcategory)->description) . '"
                    data-presentation="' . e(optional($article->presentation)->description) . '"

                    data-weight=""
                    data-cost_condition=""

                >
                    <i class="fas fa-plus"></i>
                    Agregar
                </button>
            ';
            })

            ->rawColumns(['action'])

            ->make(true);
    }
}
