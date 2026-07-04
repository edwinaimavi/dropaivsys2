<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\Currency;
use App\Models\Supplier;
use App\Models\Ubigeo;

use Illuminate\Http\Request;

use Yajra\DataTables\Facades\DataTables;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SupplierController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:admin.suppliers.index')->only(['index', 'list', 'searchUbigeo', 'consultarRuc']);
        $this->middleware('can:admin.suppliers.store')->only(['store']);
        $this->middleware('can:admin.suppliers.update')->only(['update']);
        $this->middleware('can:admin.suppliers.destroy')->only(['destroy']);
        $this->middleware('can:admin.suppliers.accounts')->only(['accounts']);
    }

    /**
     * =========================================================
     * INDEX
     * =========================================================
     */
    public function index()
    {
        $banks = Bank::where('status', 'ACTIVE')->get();

        $currencies = Currency::where('status', 'ACTIVE')->get();

        return view('admin.suppliers.index', compact(
            'banks',
            'currencies'
        ));
    }

    /**
     * =========================================================
     * LIST DATATABLE
     * =========================================================
     */
    public function list()
    {
        $suppliers = Supplier::with([
            'ubigeo',
            'creator',
            'editor'
        ])
            ->orderBy('id', 'desc')
            ->get();

        return DataTables::of($suppliers)

            ->addIndexColumn()

            ->addColumn('ubigeo_name', function ($supplier) {

                return $supplier->ubigeo->full_name ?? '—';
            })

            ->editColumn('supplier_type', function ($supplier) {

                return $supplier->supplier_type;
            })

            ->editColumn('payment_condition', function ($supplier) {

                return match ($supplier->payment_condition) {

                    'CASH' => 'CONTADO',

                    'CREDIT' => 'CRÉDITO',

                    default => $supplier->payment_condition
                };
            })

            ->editColumn('status', function ($supplier) {

                $colors = [

                    'ACTIVE' => 'info',

                    'INACTIVE' => 'danger'

                ];

                $color = $colors[$supplier->status] ?? 'secondary';

                $statusText = match ($supplier->status) {

                    'ACTIVE' => 'ACTIVO',

                    'INACTIVE' => 'INACTIVO',

                    default => $supplier->status
                };

                return '
                    <span class="badge bg-' . $color . ' text-light rounded-pill px-3 py-2 shadow-sm">
                        ' . $statusText . '
                    </span>
                ';
            })

            ->addColumn('acciones', function ($supplier) {

                return view(
                    'admin.suppliers.partials.acciones',
                    compact('supplier')
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
     * SEARCH UBIGEO
     * =========================================================
     */
    public function searchUbigeo(Request $request)
    {
        $search = $request->get('search');

        $ubigeos = Ubigeo::query()

            ->where('status', 'ACTIVE')

            ->when($search, function ($query) use ($search) {

                $query->where(function ($q) use ($search) {

                    $q->where('full_name', 'LIKE', "%{$search}%")
                        ->orWhere('code', 'LIKE', "%{$search}%");
                });
            })

            ->limit(30)

            ->get();

        $results = [];

        foreach ($ubigeos as $ubigeo) {

            $results[] = [

                'id' => $ubigeo->id,

                'text' => $ubigeo->full_name
            ];
        }

        return response()->json($results);
    }

    /**
     * =========================================================
     * STORE
     * =========================================================
     */
    public function store(Request $request)
    {
        $validated = $request->validate([

            'ruc' => [
                'required',
                'digits:11',
                'unique:suppliers,ruc'
            ],

            'business_name' => [
                'required',
                'string',
                'max:255'
            ],

            'short_name' => [
                'nullable',
                'string',
                'max:255'
            ],

            'address' => [
                'nullable',
                'string',
                'max:255'
            ],

            'ubigeo_id' => [
                'nullable',
                'exists:ubigeos,id'
            ],

            'supplier_type' => [
                'required',
                'in:NACIONAL,IMPORTADOR,DISTRIBUIDOR,FABRICANTE,LABORATORIO,OTRO'
            ],

            'payment_condition' => [
                'required',
                'string',
                'max:100'
            ],

            'contact_name' => [
                'nullable',
                'string',
                'max:255'
            ],

            'email' => [
                'nullable',
                'email',
                'max:255'
            ],

            'phone' => [
                'nullable',
                'string',
                'max:20'
            ],

            'igv_percentage' => [
                'required',
                'numeric',
                'min:0'
            ],

            'observation' => [
                'nullable',
                'string'
            ],

            'status' => [
                'required',
                'in:ACTIVE,INACTIVE'
            ],

        ], [

            'ruc.required' =>
            'El RUC es obligatorio.',

            'ruc.digits' =>
            'El RUC debe tener 11 dígitos.',

            'ruc.unique' =>
            'El RUC ya existe.',

            'business_name.required' =>
            'La razón social es obligatoria.',

            'supplier_type.required' =>
            'Debe seleccionar el tipo proveedor.',

            'supplier_type.in' =>
            'El tipo proveedor seleccionado no es válido.',

            'payment_condition.required' =>
            'Debe seleccionar la condición de pago.',

            'igv_percentage.required' =>
            'Debe ingresar el IGV.',

            'status.required' =>
            'Debe seleccionar un estado.',
        ]);

        $validated['business_name'] =
            mb_strtoupper($validated['business_name']);

        if (!empty($validated['short_name'])) {

            $validated['short_name'] =
                mb_strtoupper($validated['short_name']);
        }

        if (!empty($validated['address'])) {

            $validated['address'] =
                mb_strtoupper($validated['address']);
        }

        if (!empty($validated['contact_name'])) {

            $validated['contact_name'] =
                mb_strtoupper($validated['contact_name']);
        }

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

            $supplier = Supplier::create($validated);

            DB::commit();

            return response()->json([

                'status' => 'success',

                'message' =>
                'Proveedor registrado correctamente.',

                'data' => $supplier

            ], 201);
        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error(
                'Error creating supplier: ' .
                    $e->getMessage()
            );

            return response()->json([

                'status' => 'error',

                'message' =>
                'Error al registrar el proveedor.',

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
        $supplier = Supplier::with('ubigeo')->find($id);

        if (!$supplier) {

            return response()->json([

                'status' => 'error',

                'message' =>
                'Proveedor no encontrado.'

            ], 404);
        }

        return response()->json([

            'status' => 'success',

            'data' => $supplier

        ]);
    }

    /**
     * =========================================================
     * UPDATE
     * =========================================================
     */
    public function update(Request $request, $id)
    {
        $supplier = Supplier::find($id);

        if (!$supplier) {

            return response()->json([

                'status' => 'error',

                'message' =>
                'Proveedor no encontrado.'

            ], 404);
        }

        $validated = $request->validate([

            'ruc' => [
                'required',
                'digits:11',
                'unique:suppliers,ruc,' . $supplier->id
            ],

            'business_name' => [
                'required',
                'string',
                'max:255'
            ],

            'short_name' => [
                'nullable',
                'string',
                'max:255'
            ],

            'address' => [
                'nullable',
                'string',
                'max:255'
            ],

            'ubigeo_id' => [
                'nullable',
                'exists:ubigeos,id'
            ],

            'supplier_type' => [
                'required',
                'in:NACIONAL,IMPORTADOR,DISTRIBUIDOR,FABRICANTE,LABORATORIO,OTRO'
            ],

            'payment_condition' => [
                'required',
                'string',
                'max:100'
            ],

            'contact_name' => [
                'nullable',
                'string',
                'max:255'
            ],

            'email' => [
                'nullable',
                'email',
                'max:255'
            ],

            'phone' => [
                'nullable',
                'string',
                'max:20'
            ],

            'igv_percentage' => [
                'required',
                'numeric',
                'min:0'
            ],

            'observation' => [
                'nullable',
                'string'
            ],

            'status' => [
                'required',
                'in:ACTIVE,INACTIVE'
            ],

        ], [

            'ruc.required' =>
            'El RUC es obligatorio.',

            'ruc.digits' =>
            'El RUC debe tener 11 dígitos.',

            'ruc.unique' =>
            'El RUC ya existe.',

            'business_name.required' =>
            'La razón social es obligatoria.',

            'supplier_type.required' =>
            'Debe seleccionar el tipo proveedor.',

            'supplier_type.in' =>
            'El tipo proveedor seleccionado no es válido.',

            'payment_condition.required' =>
            'Debe seleccionar la condición de pago.',

            'igv_percentage.required' =>
            'Debe ingresar el IGV.',

            'status.required' =>
            'Debe seleccionar un estado.',
        ]);

        $validated['business_name'] =
            mb_strtoupper($validated['business_name']);

        if (!empty($validated['short_name'])) {

            $validated['short_name'] =
                mb_strtoupper($validated['short_name']);
        }

        if (!empty($validated['address'])) {

            $validated['address'] =
                mb_strtoupper($validated['address']);
        }

        if (!empty($validated['contact_name'])) {

            $validated['contact_name'] =
                mb_strtoupper($validated['contact_name']);
        }

        if (!empty($validated['observation'])) {

            $validated['observation'] =
                mb_strtoupper($validated['observation']);
        }

        try {

            DB::beginTransaction();

            if (Auth::check()) {

                $validated['updated_by'] = Auth::id();
            }

            $supplier->update($validated);

            DB::commit();

            return response()->json([

                'status' => 'success',

                'message' =>
                'Proveedor actualizado correctamente.',

                'data' => $supplier->fresh()

            ]);
        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error(
                'Error updating supplier: ' .
                    $e->getMessage()
            );

            return response()->json([

                'status' => 'error',

                'message' =>
                'Error al actualizar el proveedor.',

                'error' => $e->getMessage()

            ], 500);
        }
    }

    /**
     * =========================================================
     * DELETE
     * =========================================================
     */
    public function destroy(Supplier $supplier)
    {
        DB::beginTransaction();

        try {

            if (Auth::check()) {

                $supplier->deleted_by = Auth::id();

                $supplier->save();
            }

            $supplier->delete();

            DB::commit();

            return response()->json([

                'message' =>
                'Proveedor eliminado correctamente.'

            ]);
        } catch (\Throwable $e) {

            DB::rollBack();

            return response()->json([

                'message' =>
                'Error al eliminar el proveedor.',

                'error' => $e->getMessage()

            ], 500);
        }
    }


    public function consultarRuc($numero)
    {
        if (!preg_match('/^\d+$/', $numero)) {
            return response()->json([
                'status'  => false,
                'message' => 'El RUC debe contener solo dígitos.'
            ], 422);
        }

        if (strlen($numero) !== 11) {
            return response()->json([
                'status'  => false,
                'message' => 'El RUC debe tener 11 dígitos.'
            ], 422);
        }

        $token = 'apis-token-7645.70qIyk7rGHUBVYCLNlcITcM1fo-mBqvp'; // mejor en .env, no hardcodeado

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://api.apis.net.pe/v2/sunat/ruc?numero=' . $numero,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Authorization: Bearer ' . $token,
            ],
        ]);

        $response = curl_exec($curl);

        if ($response === false) {
            $error = curl_error($curl);
            curl_close($curl);

            return response()->json([
                'status'  => false,
                'message' => 'Error al conectar con el servicio de RUC.',
                'error'   => $error,
            ], 500);
        }

        curl_close($curl);
        $empresa = json_decode($response);

        // VALIDAR SI NO EXISTE
        if (
            !$empresa ||
            isset($empresa->error) ||
            (
                empty($empresa->nombre) &&
                empty($empresa->razonSocial)
            )
        ) {

            return response()->json([
                'status'  => false,
                'message' => 'El RUC ingresado no existe.',
                'data'    => $empresa
            ], 404);
        }

        return response()->json([
            'status'       => true,
            'type'         => 'RUC',
            'data'         => $empresa,
            'razon_social' => $empresa->nombre
                ?? $empresa->razonSocial
                ?? '',
            'direccion' => trim(

                ($empresa->direccion ?? $empresa->domicilioFiscal ?? '')

                    .

                    (
                        !empty($empresa->distrito)
                        || !empty($empresa->provincia)
                        || !empty($empresa->departamento)

                        ? ' - ' .
                        collect([
                            $empresa->distrito ?? null,
                            $empresa->provincia ?? null,
                            $empresa->departamento ?? null
                        ])->filter()->implode(' - ')

                        : ''
                    )

            ),
        ]);
    }


    public function accounts(Supplier $supplier)
    {
        return response()->json(
            $supplier->accounts()
                ->with(['bank', 'currency'])
                ->get()
        );
    }
}
