<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

use App\Models\Unit;

use Illuminate\Http\Request;

use Yajra\DataTables\Facades\DataTables;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UnitController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:admin.units.index')->only(['index', 'list', 'search']);
        $this->middleware('can:admin.units.store')->only(['store', 'quickStore']);
        $this->middleware('can:admin.units.update')->only(['update']);
        $this->middleware('can:admin.units.destroy')->only(['destroy']);
    }

    /**
     * =========================================================
     * INDEX
     * =========================================================
     */
    public function index()
    {
        return view('admin.units.index');
    }

    /**
     * =========================================================
     * LIST DATATABLE
     * =========================================================
     */
    public function list()
    {
        $units = Unit::with([
            'creator',
            'editor'
        ])
            ->orderBy('id', 'desc')
            ->get();

        return DataTables::of($units)

            ->addIndexColumn()

            ->editColumn('decimal_quantity', function ($unit) {

                if ($unit->decimal_quantity) {

                    return '
                        <span class="badge badge-info px-3 py-2 rounded-pill">
                            SI
                        </span>
                    ';
                }

                return '
                    <span class="badge badge-secondary px-3 py-2 rounded-pill">
                        NO
                    </span>
                ';
            })

            ->editColumn('status', function ($unit) {

                $colors = [

                    'ACTIVE' => 'success',

                    'INACTIVE' => 'danger'

                ];

                $color = $colors[$unit->status] ?? 'secondary';

                $statusText = match ($unit->status) {

                    'ACTIVE' => 'ACTIVO',

                    'INACTIVE' => 'INACTIVO',

                    default => $unit->status
                };

                return '
                    <span class="badge bg-' . $color . ' text-light rounded-pill px-3 py-2 shadow-sm">
                        ' . $statusText . '
                    </span>
                ';
            })

            ->addColumn('acciones', function ($unit) {

                return view(
                    'admin.units.partials.acciones',
                    compact('unit')
                )->render();
            })

            ->rawColumns([
                'decimal_quantity',
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
        $validated = $request->validate([

            'abbreviation' => [
                'required',
                'string',
                'max:20',
                'unique:units,abbreviation'
            ],

            'description' => [
                'required',
                'string',
                'max:255'
            ],

            'decimal_quantity' => [
                'required',
                'boolean'
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

            'abbreviation.required' =>
            'La abreviatura es obligatoria.',

            'abbreviation.unique' =>
            'La abreviatura ya existe.',

            'description.required' =>
            'La descripción es obligatoria.',

            'decimal_quantity.required' =>
            'Debe indicar si permite decimales.',

            'status.required' =>
            'Debe seleccionar un estado.',

        ]);

        $validated['abbreviation'] = mb_strtoupper($validated['abbreviation']);
        $validated['description'] = mb_strtoupper($validated['description']);

        if (!empty($validated['observation'])) {

            $validated['observation'] = mb_strtoupper($validated['observation']);
        }

        try {

            DB::beginTransaction();

            if (Auth::check()) {

                $validated['created_by'] = Auth::id();

                $validated['updated_by'] = Auth::id();
            }

            $unit = Unit::create($validated);

            DB::commit();

            return response()->json([

                'status' => 'success',

                'message' => 'Unidad registrada correctamente.',

                'data' => $unit

            ], 201);
        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error(
                'Error creating unit: ' . $e->getMessage()
            );

            return response()->json([

                'status' => 'error',

                'message' => 'Error al registrar la unidad.',

                'error' => $e->getMessage()

            ], 500);
        }
    }

    public function quickStore(Request $request)
    {
        $validated = $request->validate([
            'abbreviation' => ['required', 'string', 'max:20'],
            'description' => ['required', 'string', 'max:255'],
            'decimal_quantity' => ['required', 'boolean'],
        ], [
            'abbreviation.required' => 'La abreviatura es obligatoria.',
            'abbreviation.max' => 'La abreviatura no debe superar los 20 caracteres.',
            'description.required' => 'La unidad es obligatoria.',
            'description.max' => 'La descripción no debe superar los 255 caracteres.',
            'decimal_quantity.required' => 'Debe indicar si la unidad permite decimales.',
            'decimal_quantity.boolean' => 'La opción de decimales no es válida.',
        ]);

        $validated['abbreviation'] = mb_strtoupper(trim($validated['abbreviation']));
        $validated['description'] = mb_strtoupper(trim($validated['description']));

        if (Unit::withTrashed()->where('abbreviation', $validated['abbreviation'])->exists()) {
            return response()->json([
                'message' => 'Ya existe una unidad con esa abreviatura.',
                'errors' => ['abbreviation' => ['Ya existe una unidad con esa abreviatura.']],
            ], 422);
        }

        try {
            $unit = DB::transaction(function () use ($validated) {
                return Unit::create($validated + [
                    'status' => 'ACTIVE',
                    'created_by' => Auth::id(),
                    'updated_by' => Auth::id(),
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Unidad registrada correctamente.',
                'data' => [
                    'id' => $unit->id,
                    'text' => $unit->description,
                    'description' => $unit->description,
                    'abbreviation' => $unit->abbreviation,
                ],
            ], 201);
        } catch (\Throwable $e) {
            Log::error('Error quick creating unit.', ['exception' => $e]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo registrar la unidad. Inténtelo nuevamente.',
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
        $unit = Unit::find($id);

        if (!$unit) {

            return response()->json([

                'status' => 'error',

                'message' => 'Unidad no encontrada.'

            ], 404);
        }

        return response()->json([

            'status' => 'success',

            'data' => $unit

        ]);
    }

    /**
     * =========================================================
     * UPDATE
     * =========================================================
     */
    public function update(Request $request, $id)
    {
        $unit = Unit::find($id);

        if (!$unit) {

            return response()->json([

                'status' => 'error',

                'message' => 'Unidad no encontrada.'

            ], 404);
        }

        $validated = $request->validate([

            'abbreviation' => [
                'required',
                'string',
                'max:20',
                'unique:units,abbreviation,' . $unit->id
            ],

            'description' => [
                'required',
                'string',
                'max:255'
            ],

            'decimal_quantity' => [
                'required',
                'boolean'
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

            'abbreviation.required' =>
            'La abreviatura es obligatoria.',

            'abbreviation.unique' =>
            'La abreviatura ya existe.',

            'description.required' =>
            'La descripción es obligatoria.',

            'decimal_quantity.required' =>
            'Debe indicar si permite decimales.',

            'status.required' =>
            'Debe seleccionar un estado.',

        ]);

        $validated['abbreviation'] = mb_strtoupper($validated['abbreviation']);
        $validated['description'] = mb_strtoupper($validated['description']);

        if (!empty($validated['observation'])) {

            $validated['observation'] = mb_strtoupper($validated['observation']);
        }

        try {

            DB::beginTransaction();

            if (Auth::check()) {

                $validated['updated_by'] = Auth::id();
            }

            $unit->update($validated);

            DB::commit();

            return response()->json([

                'status' => 'success',

                'message' => 'Unidad actualizada correctamente.',

                'data' => $unit->fresh()

            ]);
        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error(
                'Error updating unit: ' . $e->getMessage()
            );

            return response()->json([

                'status' => 'error',

                'message' => 'Error al actualizar la unidad.',

                'error' => $e->getMessage()

            ], 500);
        }
    }

    /**
     * =========================================================
     * DELETE
     * =========================================================
     */
    public function destroy(Unit $unit)
    {
        DB::beginTransaction();

        try {

            if (Auth::check()) {

                $unit->deleted_by = Auth::id();

                $unit->save();
            }

            $unit->delete();

            DB::commit();

            return response()->json([

                'message' => 'Unidad eliminada correctamente.'

            ]);
        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([

                'message' => 'Error al eliminar la unidad.',

                'error' => $e->getMessage()

            ], 500);
        }
    }


    /**
     * =========================================================
     * SEARCH SELECT2
     * =========================================================
     */
    public function search(Request $request)
    {
        $term = trim($request->get('q', ''));

        $query = Unit::query()
            ->where('status', 'ACTIVE');

        if ($term !== '') {

            $query->where(function ($q) use ($term) {

                $q->where('abbreviation', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%");
            });
        }

        $units = $query
            ->orderBy('description')
            ->limit(20)
            ->get([
                'id',
                'abbreviation',
                'description'
            ]);

        return response()->json(

            $units->map(function ($unit) {

                return [

                    'id' => $unit->id,

                    'text' =>
                    $unit->abbreviation .
                        ' | ' .
                        $unit->description

                ];
            })

        );
    }
}
