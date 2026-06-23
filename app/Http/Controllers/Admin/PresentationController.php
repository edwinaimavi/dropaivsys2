<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

use App\Models\Presentation;

use App\Models\Unit;

use Illuminate\Http\Request;

use Yajra\DataTables\Facades\DataTables;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PresentationController extends Controller
{
    /**
     * =========================================================
     * INDEX
     * =========================================================
     */
    public function index()
    {
        $units = Unit::active()
            ->orderBy('description')
            ->get();

        return view(
            'admin.presentations.index',
            compact('units')
        );
    }

    /**
     * =========================================================
     * LIST DATATABLE
     * =========================================================
     */
    public function list()
    {
        $presentations = Presentation::with([
            'unit',
            'creator',
            'editor'
        ])
            ->orderBy('id', 'desc')
            ->get();

        return DataTables::of($presentations)

            ->addIndexColumn()

            ->addColumn('unit_name', function ($presentation) {

                return $presentation->unit->description ?? '—';
            })

            ->editColumn('quantity', function ($presentation) {

                return number_format(
                    $presentation->quantity,
                    2
                );
            })

            ->editColumn('status', function ($presentation) {

                $colors = [

                    'ACTIVE' => 'warning',

                    'INACTIVE' => 'danger'

                ];

                $color = $colors[$presentation->status] ?? 'secondary';

                $statusText = match ($presentation->status) {

                    'ACTIVE' => 'ACTIVO',

                    'INACTIVE' => 'INACTIVO',

                    default => $presentation->status
                };

                return '
                    <span class="badge bg-' . $color . ' text-light rounded-pill px-3 py-2 shadow-sm">
                        ' . $statusText . '
                    </span>
                ';
            })

            ->addColumn('acciones', function ($presentation) {

                return view(
                    'admin.presentations.partials.acciones',
                    compact('presentation')
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
        $validated = $request->validate([

            'description' => [
                'required',
                'string',
                'max:255'
            ],

            'quantity' => [
                'required',
                'numeric',
                'min:0.01'
            ],

            'unit_id' => [
                'required',
                'exists:units,id'
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

            'quantity.required' =>
            'La cantidad es obligatoria.',

            'unit_id.required' =>
            'Debe seleccionar una unidad.',

            'unit_id.exists' =>
            'La unidad seleccionada no existe.',

            'status.required' =>
            'Debe seleccionar un estado.',

        ]);

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

            $presentation = Presentation::create($validated);

            DB::commit();

            return response()->json([

                'status' => 'success',

                'message' =>
                'Presentación registrada correctamente.',

                'data' => $presentation

            ], 201);
        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error(
                'Error creating presentation: ' .
                    $e->getMessage()
            );

            return response()->json([

                'status' => 'error',

                'message' =>
                'Error al registrar la presentación.',

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
        $presentation = Presentation::find($id);

        if (!$presentation) {

            return response()->json([

                'status' => 'error',

                'message' =>
                'Presentación no encontrada.'

            ], 404);
        }

        return response()->json([

            'status' => 'success',

            'data' => $presentation

        ]);
    }

    /**
     * =========================================================
     * UPDATE
     * =========================================================
     */
    public function update(Request $request, $id)
    {
        $presentation = Presentation::find($id);

        if (!$presentation) {

            return response()->json([

                'status' => 'error',

                'message' =>
                'Presentación no encontrada.'

            ], 404);
        }

        $validated = $request->validate([

            'description' => [
                'required',
                'string',
                'max:255'
            ],

            'quantity' => [
                'required',
                'numeric',
                'min:0.01'
            ],

            'unit_id' => [
                'required',
                'exists:units,id'
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

            'quantity.required' =>
            'La cantidad es obligatoria.',

            'unit_id.required' =>
            'Debe seleccionar una unidad.',

            'unit_id.exists' =>
            'La unidad seleccionada no existe.',

            'status.required' =>
            'Debe seleccionar un estado.',

        ]);

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

            $presentation->update($validated);

            DB::commit();

            return response()->json([

                'status' => 'success',

                'message' =>
                'Presentación actualizada correctamente.',

                'data' => $presentation->fresh()

            ]);
        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error(
                'Error updating presentation: ' .
                    $e->getMessage()
            );

            return response()->json([

                'status' => 'error',

                'message' =>
                'Error al actualizar la presentación.',

                'error' => $e->getMessage()

            ], 500);
        }
    }

    /**
     * =========================================================
     * DELETE
     * =========================================================
     */
    public function destroy(Presentation $presentation)
    {
        DB::beginTransaction();

        try {

            if (Auth::check()) {

                $presentation->deleted_by = Auth::id();

                $presentation->save();
            }

            $presentation->delete();

            DB::commit();

            return response()->json([

                'message' =>
                'Presentación eliminada correctamente.'

            ]);
        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([

                'message' =>
                'Error al eliminar la presentación.',

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

        $query = Presentation::query()
            ->where('status', 'ACTIVE');

        if ($term !== '') {

            $query->where(function ($q) use ($term) {

                $q->where('description', 'like', "%{$term}%");
            });
        }

        $presentations = $query
            ->with('unit')
            ->orderBy('description')
            ->limit(20)
            ->get();

        return response()->json(

            $presentations->map(function ($presentation) {

                return [

                    'id' => $presentation->id,

                    'text' =>
                    $presentation->description .
                        ' (' .
                        $presentation->quantity .
                        ' ' .
                        ($presentation->unit->abbreviation ?? '') .
                        ')'

                ];
            })

        );
    }
}
