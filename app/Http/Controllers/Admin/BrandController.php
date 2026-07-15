<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

use App\Models\Brand;

use Illuminate\Http\Request;

use Yajra\DataTables\Facades\DataTables;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;


class BrandController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:admin.brands.index')->only(['index', 'list', 'generateCode', 'search']);
        $this->middleware('can:admin.brands.store')->only(['store', 'quickStore']);
        $this->middleware('can:admin.brands.update')->only(['update']);
        $this->middleware('can:admin.brands.destroy')->only(['destroy']);
    }

    /**
     * =========================================================
     * INDEX
     * =========================================================
     */
    public function index()
    {
        return view('admin.brands.index');
    }

    /**
     * =========================================================
     * LIST DATATABLE
     * =========================================================
     */
    public function list()
    {
        $brands = Brand::with([
            'creator',
            'editor'
        ])
            ->orderBy('id', 'desc')
            ->get();

        return DataTables::of($brands)

            ->addIndexColumn()

            ->editColumn('status', function ($brand) {

                $colors = [

                    'ACTIVE' => 'secondary',

                    'INACTIVE' => 'danger'

                ];

                $color = $colors[$brand->status] ?? 'secondary';

                $statusText = match ($brand->status) {

                    'ACTIVE' => 'ACTIVO',

                    'INACTIVE' => 'INACTIVO',

                    default => $brand->status
                };

                return '
                    <span class="badge bg-' . $color . ' text-light rounded-pill px-3 py-2 shadow-sm">
                        ' . $statusText . '
                    </span>
                ';
            })

            ->addColumn('acciones', function ($brand) {

                return view(
                    'admin.brands.partials.acciones',
                    compact('brand')
                )->render();
            })

            ->rawColumns([
                'status',
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
        $request->merge([
            'description' => mb_strtoupper(
                trim((string) $request->input('description')),
                'UTF-8'
            ),
        ]);

        $validated = $request->validate([

            'code' => [
                'required',
                'string',
                'max:50',
                'unique:brands,code'
            ],

            'description' => [
                'required',
                'string',
                'max:255',
                Rule::unique('brands', 'description')
                    ->whereNull('deleted_at')
            ],

            'observation' => [
                'nullable',
                'string'
            ],

            'status' => [
                'required',
                'in:ACTIVE,INACTIVE'
            ]

        ], [

            'code.required' =>
            'El código es obligatorio.',

            'code.unique' =>
            'El código ya existe.',

            'description.required' =>
            'La descripción es obligatoria.',

            'description.unique' =>
            "La marca ya est\u{00E1} registrada.",

            'status.required' =>
            'Debe seleccionar un estado.',
        ]);

        $validated['code'] =
            mb_strtoupper($validated['code']);

        $validated['description'] =
            mb_strtoupper($validated['description']);

        if (!empty($validated['observation'])) {

            $validated['observation'] =
                mb_strtoupper($validated['observation']);
        }

        try {

            DB::beginTransaction();

            if (Auth::check()) {

                $validated['created_by'] = Auth::id();

                $validated['updated_by'] = Auth::id();
            }

            $brand = Brand::create($validated);

            DB::commit();

            return response()->json([

                'status' => 'success',

                'message' =>
                'Marca registrada correctamente.',

                'data' => $brand

            ], 201);
        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error(
                'Error creating brand: ' .
                    $e->getMessage()
            );

            return response()->json([

                'status' => 'error',

                'message' =>
                'Error al registrar la marca.',

                'error' => $e->getMessage()

            ], 500);
        }
    }

    public function quickStore(Request $request)
    {
        $request->merge([
            'description' => mb_strtoupper(
                trim((string) ($request->input('description') ?: $request->input('name'))),
                'UTF-8'
            ),
            'status' => $request->input('status') ?: 'ACTIVE',
        ]);

        $validated = $request->validate([
            'description' => [
                'required',
                'string',
                'max:255',
                Rule::unique('brands', 'description')->whereNull('deleted_at'),
            ],
            'observation' => ['nullable', 'string'],
            'status' => ['required', 'in:ACTIVE,INACTIVE'],
        ], [
            'description.required' => 'El nombre de la marca es obligatorio.',
            'description.unique' => 'La marca ya está registrada.',
        ]);

        try {
            DB::beginTransaction();

            $validated['code'] = $this->nextBrandCode();
            $validated['description'] = mb_strtoupper($validated['description'], 'UTF-8');
            $validated['observation'] = !empty($validated['observation'])
                ? mb_strtoupper($validated['observation'], 'UTF-8')
                : null;
            $validated['created_by'] = Auth::id();
            $validated['updated_by'] = Auth::id();

            $brand = Brand::create($validated);

            DB::commit();

            $brandPayload = [
                'id' => $brand->id,
                'name' => $brand->description,
                'description' => $brand->description,
                'text' => $brand->description,
                'status' => $brand->status,
            ];

            return response()->json([
                'success' => true,
                'status' => 'success',
                'message' => 'Marca registrada correctamente.',
                'brand' => $brandPayload,
                'data' => $brandPayload,
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Error quick creating brand: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'No se pudo registrar la marca.',
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
        $brand = Brand::find($id);

        if (!$brand) {

            return response()->json([

                'status' => 'error',

                'message' =>
                'Marca no encontrada.'

            ], 404);
        }

        return response()->json([

            'status' => 'success',

            'data' => $brand

        ]);
    }

    /**
     * =========================================================
     * UPDATE
     * =========================================================
     */
    public function update(Request $request, $id)
    {
        $brand = Brand::find($id);

        if (!$brand) {

            return response()->json([

                'status' => 'error',

                'message' =>
                'Marca no encontrada.'

            ], 404);
        }

        $request->merge([
            'description' => mb_strtoupper(
                trim((string) $request->input('description')),
                'UTF-8'
            ),
        ]);

        $validated = $request->validate([

            'code' => [
                'required',
                'string',
                'max:50',
                'unique:brands,code,' . $brand->id
            ],

            'description' => [
                'required',
                'string',
                'max:255',
                Rule::unique('brands', 'description')
                    ->ignore($brand->id)
                    ->whereNull('deleted_at')
            ],

            'observation' => [
                'nullable',
                'string'
            ],

            'status' => [
                'required',
                'in:ACTIVE,INACTIVE'
            ]

        ], [

            'code.required' =>
            'El código es obligatorio.',

            'code.unique' =>
            'El código ya existe.',

            'description.required' =>
            'La descripción es obligatoria.',

            'description.unique' =>
            "La marca ya est\u{00E1} registrada.",

            'status.required' =>
            'Debe seleccionar un estado.',
        ]);

        $validated['code'] =
            mb_strtoupper($validated['code']);

        $validated['description'] =
            mb_strtoupper($validated['description']);

        if (!empty($validated['observation'])) {

            $validated['observation'] =
                mb_strtoupper($validated['observation']);
        }

        try {

            DB::beginTransaction();

            if (Auth::check()) {

                $validated['updated_by'] = Auth::id();
            }

            $brand->update($validated);

            DB::commit();

            return response()->json([

                'status' => 'success',

                'message' =>
                'Marca actualizada correctamente.',

                'data' => $brand->fresh()

            ]);
        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error(
                'Error updating brand: ' .
                    $e->getMessage()
            );

            return response()->json([

                'status' => 'error',

                'message' =>
                'Error al actualizar la marca.',

                'error' => $e->getMessage()

            ], 500);
        }
    }

    /**
     * =========================================================
     * DELETE
     * =========================================================
     */
    public function destroy(Brand $brand)
    {
        DB::beginTransaction();

        try {

            if (Auth::check()) {

                $brand->deleted_by = Auth::id();

                $brand->save();
            }

            $brand->delete();

            DB::commit();

            return response()->json([

                'message' =>
                'Marca eliminada correctamente.'

            ]);
        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([

                'message' =>
                'Error al eliminar la marca.',

                'error' => $e->getMessage()

            ], 500);
        }
    }


    public function generateCode()
    {
        return response()->json([
            'code' => $this->nextBrandCode()
        ]);
    }

    private function nextBrandCode(): string
    {
        $lastBrand = Brand::withTrashed()
            ->orderBy('id', 'desc')
            ->first();

        $nextNumber = $lastBrand
            ? $lastBrand->id + 1
            : 1;

        return 'BRA' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
    }


 

    public function search(Request $request)
    {
        $term = trim($request->get('q', ''));

        $query = Brand::query()
            ->where('status', 'ACTIVE');

        if ($term !== '') {
            $query->where(function ($q) use ($term) {
                $q->where('code', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%");
            });
        }

        $brands = $query
            ->orderBy('description')
            ->limit(20)
            ->get(['id', 'code', 'description']);

        return response()->json(
            $brands->map(function ($brand) {
                return [
                    'id' => $brand->id,
                    'text' => $brand->code . ' | ' . $brand->description,
                ];
            })
        );
    }
}
