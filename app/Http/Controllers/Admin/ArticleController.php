<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

use App\Models\Article;
use App\Models\Brand as ModelsBrand;
use Yajra\DataTables\Facades\DataTables;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
    public function __construct()
    {
        $this->middleware('can:admin.articles.index')->only(['index', 'list', 'generateCode', 'getSubcategories', 'listPicker']);
        $this->middleware('can:admin.articles.store')->only(['store', 'quickStore']);
        $this->middleware('can:admin.articles.update')->only(['update']);
        $this->middleware('can:admin.articles.destroy')->only(['destroy']);
        $this->middleware('can:admin.articles.show')->only(['show', 'showData']);
    }

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
            ->orderBy('id', 'desc');

        return DataTables::eloquent($articles)

            ->filter(function ($query) {
                $search = trim((string) request('search.value', ''));

                if ($search === '') {
                    return;
                }

                $query->where(function ($articleQuery) use ($search) {
                    $like = '%' . $search . '%';

                    $articleQuery
                        ->where('code', 'like', $like)
                        ->orWhere('code_type', 'like', $like)
                        ->orWhere('institutional_code', 'like', $like)
                        ->orWhere('legal_name', 'like', $like)
                        ->orWhere('commercial_name', 'like', $like)
                        ->orWhere('billing_name', 'like', $like)
                        ->orWhereHas('brand', function ($brandQuery) use ($like) {
                            $brandQuery->where('description', 'like', $like);
                        });
                });
            })

            ->addIndexColumn()

            ->addColumn('brand', function ($article) {

                return $article->brand?->description ?? '-';
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
                'nullable',
                'json'
            ],
            'documents_files.*' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'images.*' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:4096'
            ],

        ]);

        $documentsData = $this->validatedDocumentData($request);
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
            $validated['brand_id'] = null;

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

                            'brand_id' =>
                            $doc['brand_id'] ?? null,

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

    public function quickStore(Request $request)
    {
        $requiresClassification = $request->input('quick_context') === 'customer_purchase_order';
        $baseName = $this->normalizeArticleText(
            $request->input('legal_name')
                ?: $request->input('billing_name')
                ?: $request->input('commercial_name')
                ?: $request->input('name')
        );

        $request->merge([
            'code' => $request->input('code') ?: $this->nextArticleCode(),
            'code_type' => $request->input('code_type') ?: 'SIGA/SISMED',
            'legal_name' => $request->input('legal_name') ?: $baseName,
            'commercial_name' => $request->input('commercial_name') ?: $baseName,
            'billing_name' => $request->input('billing_name') ?: $request->input('commercial_name') ?: $baseName,
            'status' => $request->input('status') ?: 'ACTIVE',
            'is_taxable' => $request->input('is_taxable', 1),
            'has_batch' => $request->input('has_batch', 0),
            'has_expiration' => $request->input('has_expiration', 0),
            'minimum_stock' => $request->input('minimum_stock', 0),
        ]);

        $code = mb_strtoupper((string) $request->input('code'), 'UTF-8');
        if (Article::withTrashed()->where('code', $code)->exists()) {
            $code = $this->nextArticleCode();
        }

        $request->merge(['code' => $code]);

        $this->normalizeArticleNames($request);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:articles,code'],
            'code_type' => ['nullable', 'in:SIGA/SISMED,SAP/IETSI'],
            'institutional_code' => ['nullable', 'string', 'max:100'],
            'legal_name' => ['required', 'max:255'],
            'commercial_name' => ['nullable', 'max:255'],
            'billing_name' => ['required', 'max:255'],
            'presentation_id' => [Rule::requiredIf($requiresClassification), 'nullable', 'exists:presentations,id'],
            'unit_id' => [Rule::requiredIf($requiresClassification), 'nullable', 'exists:units,id'],
            'brand_id' => ['nullable', 'exists:brands,id'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'subcategory_id' => ['nullable', 'exists:subcategories,id'],
        ], [
            'code.required' => 'El código del artículo es obligatorio.',
            'code.unique' => 'El código del artículo ya existe.',
            'code_type.in' => 'El tipo de código seleccionado no es válido.',
            'institutional_code.max' => 'El código institucional no debe superar 100 caracteres.',
            'billing_name.required' => 'El nombre de facturación es obligatorio.',
            'legal_name.required' => 'El nombre legal es obligatorio.',
            'presentation_id.required' => 'La presentación es obligatoria.',
            'presentation_id.exists' => 'La presentación seleccionada no es válida.',
            'unit_id.required' => 'La unidad es obligatoria.',
            'unit_id.exists' => 'La unidad seleccionada no es válida.',
        ]);

        $this->validateDuplicateArticleName($validated);

        $defaultCategory = Category::where('status', 'ACTIVE')
            ->orderBy('id')
            ->first();
        $defaultUnit = Unit::where('status', 'ACTIVE')
            ->orderBy('id')
            ->first();

        if ((!$defaultCategory && empty($validated['category_id'])) || (!$defaultUnit && empty($validated['unit_id']))) {
            throw ValidationException::withMessages([
                'article' => 'Debe registrar al menos una categoría y una unidad activas antes de crear artículos rápidos.',
            ]);
        }

        try {
            DB::beginTransaction();

            $validated['code'] = mb_strtoupper($validated['code'], 'UTF-8');
            $validated['institutional_code'] = !empty($validated['institutional_code'])
                ? mb_strtoupper($validated['institutional_code'], 'UTF-8')
                : null;
            $validated['category_id'] = $validated['category_id'] ?? $defaultCategory->id;
            $validated['subcategory_id'] = $validated['subcategory_id'] ?? null;
            $validated['presentation_id'] = $validated['presentation_id'] ?? null;
            $validated['unit_id'] = $validated['unit_id'] ?? $defaultUnit->id;
            $validated['brand_id'] = $validated['brand_id'] ?? null;
            $validated['status'] = 'ACTIVE';
            $validated['is_taxable'] = 1;
            $validated['has_batch'] = 0;
            $validated['has_expiration'] = 0;
            $validated['minimum_stock'] = 0;
            $validated['observation'] = null;
            $validated['created_by'] = Auth::id();
            $validated['updated_by'] = Auth::id();

            $article = Article::create($validated)->fresh([
                'category',
                'subcategory',
                'unit',
                'presentation',
                'brand',
            ]);

            DB::commit();

            $articlePayload = [
                'id' => $article->id,
                'code' => $article->code,
                'code_type' => $article->code_type,
                'institutional_code' => $article->institutional_code,
                'name' => $article->billing_name,
                'legal_name' => $article->legal_name,
                'commercial_name' => $article->commercial_name,
                'billing_name' => $article->billing_name,
                'invoice_name' => $article->billing_name,
                'text' => $article->code . ' | ' . $article->billing_name,
                'category_name' => $article->category?->description,
                'subcategory_name' => $article->subcategory?->description,
                'presentation_id' => $article->presentation_id,
                'presentation_name' => $article->presentation?->description,
                'unit_id' => $article->unit_id,
                'unit_name' => $article->unit?->description,
                'brand_id' => $article->brand_id,
                'brand_name' => $article->brand?->description,
                'is_taxable' => (bool) $article->is_taxable,
            ];

            return response()->json([
                'success' => true,
                'status' => 'success',
                'message' => 'Artículo registrado correctamente.',
                'article' => $articlePayload,
                'data' => $articlePayload,
            ], 201);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Error quick creating article', [
                'message' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'No se pudo registrar el artículo. Revise los datos ingresados.',
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

            'documents.documentType',
            'documents.brand'

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
            ],
            'documents_data' => ['nullable', 'json'],
            'documents_files.*' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $documentsData = $this->validatedDocumentData($request);
        $this->validateDuplicateArticleName($validated, $article->id);

        $validated['brand_id'] = null;

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

                            'brand_id' =>
                            $doc['brand_id'] ?? null,

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

    private function validatedDocumentData(Request $request): array
    {
        $documents = json_decode((string) $request->input('documents_data', '[]'), true);

        if (!is_array($documents) || $documents === []) {
            return [];
        }

        Validator::make(
            ['documents' => $documents],
            [
                'documents' => ['array'],
                'documents.*.document_type_id' => ['required', 'exists:document_types,id'],
                'documents.*.brand_id' => ['nullable', 'exists:brands,id'],
                'documents.*.issue_date' => ['nullable', 'date'],
                'documents.*.expiration_date' => ['nullable', 'date'],
                'documents.*.observation' => ['nullable', 'string'],
            ],
            [
                'documents.*.document_type_id.required' => 'Seleccione el tipo de documento.',
                'documents.*.document_type_id.exists' => 'El tipo de documento seleccionado no es válido.',
                'documents.*.brand_id.exists' => 'La marca seleccionada para el documento no es válida.',
                'documents.*.issue_date.date' => 'La fecha de emisión del documento no es válida.',
                'documents.*.expiration_date.date' => 'La fecha de vencimiento del documento no es válida.',
            ]
        )->validate();

        foreach (array_keys($documents) as $index) {
            if (!$request->hasFile("documents_files.$index")) {
                throw ValidationException::withMessages([
                    "documents_files.$index" => 'Seleccione el archivo PDF del documento.',
                ]);
            }
        }

        return $documents;
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
            ->where('brand_id', $validated['brand_id'] ?? null)
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
        return response()->json([

            'code' =>
            $this->nextArticleCode()

        ]);
    }

    private function nextArticleCode(): string
    {
        $lastArticle = Article::withTrashed()
            ->orderBy('id', 'desc')
            ->first();

        $nextNumber = $lastArticle
            ? $lastArticle->id + 1
            : 1;

        do {
            $code = 'ART' . str_pad(
                $nextNumber,
                5,
                '0',
                STR_PAD_LEFT
            );

            $nextNumber++;
        } while (Article::withTrashed()->where('code', $code)->exists());

        return $code;
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
            'documents.brand',

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
            ->where('status', 'ACTIVE')
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
                return $article->brand?->description ?? '-';
            })

            ->addColumn('cost_condition', function () {
                return '';
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
