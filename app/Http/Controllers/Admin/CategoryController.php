<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

use App\Models\Article;
use App\Models\Category;
use App\Models\Subcategory;
use Illuminate\Http\Request;

use Yajra\DataTables\Facades\DataTables;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:admin.categories.index')->only(['index', 'list', 'generateCode', 'subcategoryList']);
        $this->middleware('can:admin.categories.store')->only(['store']);
        $this->middleware('can:admin.categories.update')->only(['update']);
        $this->middleware('can:admin.categories.destroy')->only(['destroy']);
        $this->middleware('can:admin.categories.show')->only(['show']);
        $this->middleware('can:admin.subcategories.store')->only(['storeSubcategory']);
        $this->middleware('can:admin.subcategories.update')->only(['updateSubcategory']);
        $this->middleware('can:admin.subcategories.destroy')->only(['destroySubcategory']);
    }

    /**
     * INDEX
     */
    public function index()
    {
        return view('admin.categories.index');
    }


    /**
     * GENERAR CÓDIGO AUTOMÁTICO
     */
    public function generateCode()
    {
        $lastNumber = Category::withTrashed()
            ->where('code', 'like', 'CAT%')
            ->pluck('code')
            ->map(function ($code) {
                preg_match('/^CAT(\d+)$/i', trim($code), $matches);

                return isset($matches[1])
                    ? (int) $matches[1]
                    : 0;
            })
            ->max();

        return response()->json([
            'code' => 'CAT' . str_pad(($lastNumber ?? 0) + 1, 3, '0', STR_PAD_LEFT)
        ]);
    }

    /**
     * LIST DATATABLE
     */
    public function list()
    {
        $categories = Category::with([
            'creator',
            'editor',
            'subcategories'
        ])
            ->orderBy('id', 'desc')
            ->get();

        return DataTables::of($categories)

            ->addIndexColumn()

            ->editColumn('status', function ($category) {

                $colors = [

                    'ACTIVE' => 'success',

                    'INACTIVE' => 'danger'

                ];

                $color = $colors[$category->status] ?? 'secondary';

                $statusText = match ($category->status) {

                    'ACTIVE' => 'ACTIVO',

                    'INACTIVE' => 'INACTIVO',

                    default => $category->status
                };

                return '
    <span class="badge bg-' . $color . ' text-light rounded-pill px-3 py-2 shadow-sm">
        ' . $statusText . '
    </span>
';
            })

            ->addColumn('acciones', function ($category) {

                return view(
                    'admin.categories.partials.acciones',
                    compact('category')
                )->render();
            })

            ->rawColumns([
                'status',
                'acciones'
            ])

            ->make(true);
    }

    /**
     * STORE
     */
    public function store(Request $request)
    {
        $request->merge([
            'description' => mb_strtoupper(
                trim((string) $request->input('description')),
                'UTF-8'
            ),
        ]);

        $validated = $request->validate([

            'description' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categories', 'description')
                    ->whereNull('deleted_at')
            ],

            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('categories', 'code')
            ],

            'type' => [
                'required',
                'string',
                'max:100'
            ],

            'status' => [
                'required',
                'in:ACTIVE,INACTIVE'
            ],

            'observation' => [
                'nullable',
                'string'
            ]

        ], [

            // DESCRIPTION
            'description.required' =>
            'La descripción es obligatoria.',

            'description.max' =>
            'La descripción no puede superar 255 caracteres.',

            'description.unique' =>
            "La categor\u{00ED}a ya est\u{00E1} registrada.",

            // CODE
            'code.required' =>
            'El código es obligatorio.',

            'code.unique' =>
            'El código ya existe en el sistema.',

            'code.max' =>
            'El código no puede superar 20 caracteres.',

            // TYPE
            'type.required' =>
            'Debe seleccionar un tipo.',

            // STATUS
            'status.required' =>
            'Debe seleccionar un estado.',

            'status.in' =>
            'El estado seleccionado no es válido.',

        ]);

        try {

            DB::beginTransaction();

            // VALIDACIÓN EXTRA DE SEGURIDAD
            $exists = Category::withTrashed()
                ->where('code', $validated['code'])
                ->exists();

            if ($exists) {

                return response()->json([

                    'status' => 'error',

                    'errors' => [
                        'code' => [
                            'El código ya está registrado.'
                        ]
                    ]

                ], 422);
            }

            if (Auth::check()) {

                $validated['created_by'] = Auth::id();

                $validated['updated_by'] = Auth::id();
            }

            $category = Category::create($validated);

            DB::commit();

            return response()->json([

                'status' => 'success',

                'message' => 'Categoría registrada correctamente.',

                'data' => $category

            ], 201);
        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error(
                'Error creating category: ' . $e->getMessage()
            );

            return response()->json([

                'status' => 'error',

                'message' => 'Error al registrar la categoría.',

                'error' => $e->getMessage()

            ], 500);
        }
    }

    /**
     * EDIT
     */
    public function edit($id)
    {
        $category = Category::find($id);

        if (!$category) {

            return response()->json([

                'status' => 'error',

                'message' => 'Categoría no encontrada.'

            ], 404);
        }

        return response()->json([

            'status' => 'success',

            'data' => $category

        ]);
    }

    /**
     * SHOW
     */
    public function show(Category $category)
    {
        $category->load([
            'creator',
            'editor',
            'subcategories' => function ($query) {
                $query->orderBy('id', 'desc');
            },
        ]);

        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $category->id,
                'description' => $category->description,
                'code' => $category->code,
                'type' => $category->type,
                'status' => $category->status,
                'observation' => $category->observation,
                'created_at' => $category->created_at?->format('d/m/Y H:i'),
                'updated_at' => $category->updated_at?->format('d/m/Y H:i'),
                'created_by' => $category->creator?->name,
                'updated_by' => $category->editor?->name,
                'subcategories' => $category->subcategories->map(fn ($subcategory) => [
                    'id' => $subcategory->id,
                    'description' => $subcategory->description,
                    'status' => $subcategory->status,
                    'observation' => $subcategory->observation,
                    'created_at' => $subcategory->created_at?->format('d/m/Y H:i'),
                    'updated_at' => $subcategory->updated_at?->format('d/m/Y H:i'),
                ])->values(),
            ],
        ]);
    }

    /**
     * UPDATE
     */
    public function update(Request $request, $id)
    {
        $category = Category::find($id);

        if (!$category) {

            return response()->json([

                'status' => 'error',

                'message' => 'Categoría no encontrada.'

            ], 404);
        }

        $request->merge([
            'description' => mb_strtoupper(
                trim((string) $request->input('description')),
                'UTF-8'
            ),
        ]);

        $validated = $request->validate([

            'description' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categories', 'description')
                    ->ignore($category->id)
                    ->whereNull('deleted_at')
            ],

            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('categories', 'code')
                    ->ignore($category->id)
            ],

            'type' => [
                'required',
                'string',
                'max:100'
            ],

            'status' => [
                'required',
                'in:ACTIVE,INACTIVE'
            ],

            'observation' => [
                'nullable',
                'string'
            ]

        ], [

            // DESCRIPTION
            'description.required' =>
            'La descripción es obligatoria.',

            'description.max' =>
            'La descripción no puede superar 255 caracteres.',

            'description.unique' =>
            "La categor\u{00ED}a ya est\u{00E1} registrada.",

            // CODE
            'code.required' =>
            'El código es obligatorio.',

            'code.unique' =>
            'El código ya existe en el sistema.',

            'code.max' =>
            'El código no puede superar 20 caracteres.',

            // TYPE
            'type.required' =>
            'Debe seleccionar un tipo.',

            // STATUS
            'status.required' =>
            'Debe seleccionar un estado.',

            'status.in' =>
            'El estado seleccionado no es válido.',

        ]);

        try {

            DB::beginTransaction();

            // VALIDACIÓN EXTRA
            $exists = Category::withTrashed()
                ->where('code', $validated['code'])
                ->where('id', '!=', $category->id)
                ->exists();

            if ($exists) {

                return response()->json([

                    'status' => 'error',

                    'errors' => [
                        'code' => [
                            'El código ya está registrado.'
                        ]
                    ]

                ], 422);
            }

            if (Auth::check()) {

                $validated['updated_by'] = Auth::id();
            }

            $category->update($validated);

            DB::commit();

            return response()->json([

                'status' => 'success',

                'message' => 'Categoría actualizada correctamente.',

                'data' => $category->fresh()

            ]);
        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error(
                'Error updating category: ' . $e->getMessage()
            );

            return response()->json([

                'status' => 'error',

                'message' => 'Error al actualizar la categoría.',

                'error' => $e->getMessage()

            ], 500);
        }
    }

    /**
     * DELETE
     */
    public function destroy(Category $category)
    {
        DB::beginTransaction();

        try {

            if (Auth::check()) {

                $category->deleted_by = Auth::id();

                $category->save();
            }

            $category->delete();

            DB::commit();

            return response()->json([

                'message' => 'Categoría eliminada correctamente.'

            ]);
        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([

                'message' => 'Error al eliminar la categoría.',

                'error' => $e->getMessage()

            ], 500);
        }
    }


    /**
     * =========================================================
     * LISTAR SUBCATEGORÍAS
     * =========================================================
     */
    public function subcategoryList($categoryId)
    {
        $subcategories = Subcategory::where('category_id', $categoryId)
            ->orderBy('id', 'desc')
            ->get();

        return DataTables::of($subcategories)

            ->addIndexColumn()

            ->editColumn('status', function ($subcategory) {

                $color = $subcategory->status === 'ACTIVE'
                    ? 'success'
                    : 'danger';

                $text = $subcategory->status === 'ACTIVE'
                    ? 'ACTIVO'
                    : 'INACTIVO';

                return '
                <span class="badge badge-' . $color . ' px-3 py-2 rounded-pill">
                    ' . $text . '
                </span>
            ';
            })

            ->addColumn('acciones', function ($subcategory) {

                return '
                <div class="btn-group btn-group-sm">

                    <button
                        class="btn btn-outline-primary editSubcategory"
                        data-id="' . $subcategory->id . '"
                        data-description="' . $subcategory->description . '"
                        data-status="' . $subcategory->status . '"
                        data-observation="' . $subcategory->observation . '"
                    >
                        <i class="fas fa-pen"></i>
                    </button>

                    <button
                        class="btn btn-outline-danger deleteSubcategory"
                        data-id="' . $subcategory->id . '"
                    >
                        <i class="fas fa-trash"></i>
                    </button>

                </div>
            ';
            })

            ->rawColumns([
                'status',
                'acciones'
            ])

            ->make(true);
    }

    /**
     * =========================================================
     * GUARDAR SUBCATEGORÍA
     * =========================================================
     */
    public function storeSubcategory(Request $request)
    {
        $validated = $request->validate([

            'category_id' => [
                'required',
                'exists:categories,id'
            ],

            'description' => [
                'required',
                'string',
                'max:255'
            ],

            'status' => [
                'required',
                'in:ACTIVE,INACTIVE'
            ],

            'observation' => [
                'nullable',
                'string'
            ]

        ], [

            'description.required' =>
            'La descripción es obligatoria.',

            'category_id.required' =>
            'La categoría es obligatoria.',

        ]);

        try {

            DB::beginTransaction();

            if (Auth::check()) {

                $validated['created_by'] = Auth::id();

                $validated['updated_by'] = Auth::id();
            }

            Subcategory::create($validated);

            DB::commit();

            return response()->json([

                'status' => 'success',

                'message' => 'Subcategoría registrada correctamente.'

            ]);
        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([

                'status' => 'error',

                'message' => 'Error al registrar subcategoría.',

                'error' => $e->getMessage()

            ], 500);
        }
    }


    /**
     * =========================================================
     * ACTUALIZAR SUBCATEGORÍA
     * =========================================================
     */
    public function updateSubcategory(Request $request, $id)
    {
        $subcategory = Subcategory::find($id);

        if (!$subcategory) {

            return response()->json([

                'status' => 'error',

                'message' => 'Subcategoría no encontrada.'

            ], 404);
        }

        $validated = $request->validate([

            'description' => [
                'required',
                'string',
                'max:255'
            ],

            'status' => [
                'required',
                'in:ACTIVE,INACTIVE'
            ],

            'observation' => [
                'nullable',
                'string'
            ]

        ], [

            'description.required' =>
            'La descripción es obligatoria.',

        ]);

        try {

            DB::beginTransaction();

            if (Auth::check()) {

                $validated['updated_by'] = Auth::id();
            }

            $subcategory->update($validated);

            DB::commit();

            return response()->json([

                'status' => 'success',

                'message' => 'Subcategoría actualizada correctamente.'

            ]);
        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([

                'status' => 'error',

                'message' => 'Error al actualizar subcategoría.',

                'error' => $e->getMessage()

            ], 500);
        }
    }

    /**
     * =========================================================
     * ELIMINAR SUBCATEGORÍA
     * =========================================================
     */
    public function destroySubcategory($id)
    {
        $subcategory = Subcategory::find($id);

        if (!$subcategory) {

            return response()->json([

                'status' => 'error',

                'message' => 'Subcategoría no encontrada.'

            ], 404);
        }

        try {

            DB::beginTransaction();

            $hasArticles = Article::where('subcategory_id', $subcategory->id)
                ->exists();

            if ($hasArticles) {
                DB::rollBack();

                return response()->json([

                    'status' => 'error',

                    'message' => 'No se puede eliminar la subcategoría porque tiene artículos asociados.'

                ], 422);
            }

            if (Auth::check()) {

                $subcategory->deleted_by = Auth::id();

                $subcategory->save();
            }

            $subcategory->delete();

            DB::commit();

            return response()->json([

                'status' => 'success',

                'message' => 'Subcategoría eliminada correctamente.'

            ]);
        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([

                'status' => 'error',

                'message' => 'Error al eliminar subcategoría.',

                'error' => $e->getMessage()

            ], 500);
        }
    }
}
