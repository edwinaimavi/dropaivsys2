<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Ubigeo;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class CustomerController extends Controller
{
    public function index()
    {
        return view('admin.customers.index');
    }

    // ============================
    // LISTADO DATATABLE
    // ============================
    public function list()
    {
        $customers = Customer::orderByDesc('id');

        return DataTables::eloquent($customers)
            ->addIndexColumn()

            ->editColumn('full_name', function ($customer) {
                return '<span class="fw-semibold">' . e($customer->full_name) . '</span>';
            })

            ->editColumn('status_badge', function ($customer) {
                return $customer->status
                    ? '<span class="badge bg-success rounded-pill px-3 py-2">
                        <i class="bi bi-check-circle me-1"></i> Activo
                   </span>'
                    : '<span class="badge bg-secondary rounded-pill px-3 py-2">
                        <i class="bi bi-slash-circle me-1"></i> Inactivo
                   </span>';
            })

            ->addColumn('actions', function ($customer) {

                return '

    <div class="btn-group shadow-sm" role="group">

        <!-- VIEW -->
        <button type="button"
            class="btn btn-outline-info btn-sm viewCustomer"

            data-id="' . $customer->id . '"
            data-person_type="' . e($customer->person_type) . '"
            data-first_name="' . e($customer->first_name) . '"
            data-last_name="' . e($customer->last_name) . '"
            data-business_name="' . e($customer->business_name) . '"
            data-document_type="' . e($customer->document_type) . '"
            data-document_number="' . e($customer->document_number) . '"
            data-phone="' . e($customer->phone) . '"
            data-email="' . e($customer->email) . '"
            data-channel="' . e($customer->channel) . '"
data-subchannel="' . e($customer->subchannel) . '"
data-withholding_agent="' . e($customer->withholding_agent) . '"
            data-address="' . e($customer->address) . '"
            data-status="' . $customer->status . '"
            data-created_at="' . ($customer->created_at
                    ? $customer->created_at->format('d/m/Y H:i')
                    : '—') . '"

            data-updated_at="' . ($customer->updated_at
                    ? $customer->updated_at->format('d/m/Y H:i')
                    : '—') . '"

            data-created_by="' . e($customer->creator->name ?? 'No registrado') . '"

            data-updated_by="' . e($customer->updater->name ?? 'No registrado') . '"

            title="Ver Cliente">

            <i class="fas fa-eye"></i>

        </button>

        <!-- EDIT -->
        <button type="button"
            class="btn btn-outline-primary btn-sm editCustomer"

            data-id="' . $customer->id . '"
            data-person_type="' . e($customer->person_type) . '"
            data-first_name="' . e($customer->first_name) . '"
            data-last_name="' . e($customer->last_name) . '"
            data-business_name="' . e($customer->business_name) . '"
            data-full_name="' . e($customer->full_name) . '"
            data-document_type="' . e($customer->document_type) . '"
            data-document_number="' . e($customer->document_number) . '"
            
            data-phone="' . e($customer->phone) . '"
            data-email="' . e($customer->email) . '"
            data-address="' . e($customer->address) . '"
            data-channel="' . e($customer->channel) . '"
data-subchannel="' . e($customer->subchannel) . '"
data-withholding_agent="' . e($customer->withholding_agent) . '"
            data-status="' . $customer->status . '"

            title="Editar Cliente">

            <i class="fas fa-pen"></i>

        </button>

        
<button
    type="button"
    class="btn btn-outline-success btn-sm branchCustomer"

    data-id="' . $customer->id . '"
    data-person_type="' . e($customer->person_type) . '"
    data-business_name="' . e($customer->business_name) . '"
    data-first_name="' . e($customer->first_name) . '"
    data-last_name="' . e($customer->last_name) . '"
    data-document_number="' . e($customer->document_number) . '"

    title="Sedes">

    <i class="fas fa-building"></i>

</button>
        <!-- DELETE -->
        <button type="button"
            class="btn btn-outline-danger btn-sm deleteCustomer"
            data-id="' . $customer->id . '"
            title="Eliminar Cliente">

            <i class="fas fa-trash"></i>

        </button>

    </div>
    ';
            })

            ->rawColumns(['full_name', 'status_badge', 'actions'])
            ->make(true);
    }

    // ============================
    // STORE (CREAR)
    // ============================
    public function store(Request $request)
    {
        $data = $request->validate([
            'person_type' => 'required|in:natural,juridica',
            'document_type' => 'required|in:DNI,CE,RUC',
            'document_number' => [

                'required',

                'string',

                'max:20',

                function ($attribute, $value, $fail) use ($request) {

                    if (
                        $request->document_type === 'RUC' &&
                        Customer::where('ruc', $value)->exists()
                    ) {

                        $fail('El RUC ya se encuentra registrado.');
                    }
                }
            ],

            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'business_name' => 'nullable|string|max:255',

            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',

            'channel' => 'nullable|string|max:100',
            'subchannel' => 'nullable|string|max:100',
            'withholding_agent' => 'nullable|boolean',

            'status' => 'nullable|boolean',
        ]);

        // 🔥 LOGICA CLAVE
        if ($request->document_type === 'RUC') {

            if (empty($request->business_name)) {
                return response()->json([
                    'errors' => [
                        'business_name' => ['La razón social es obligatoria']
                    ]
                ], 422);
            }
        } else {

            if (empty($request->first_name) || empty($request->last_name)) {
                return response()->json([
                    'errors' => [
                        'first_name' => ['Nombres y apellidos son obligatorios']
                    ]
                ], 422);
            }
        }

        if ($data['document_type'] === 'RUC') {

            $data['first_name'] = null;
            $data['last_name'] = null;
            $data['full_name'] = $data['business_name'];
            $data['ruc'] = $data['document_number'];
        } else {

            $data['business_name'] = null;
            $data['ruc'] = null;
            $data['full_name'] = $data['first_name'] . ' ' . $data['last_name'];
        }

        $data['status'] = (int) $request->status;

        $data['withholding_agent'] =
            $request->withholding_agent ?? 0;

        try {

            DB::beginTransaction();

            if (Auth::check()) {

                $data['created_by'] = Auth::id();

                $data['updated_by'] = Auth::id();
            }

            $customer = Customer::create($data);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Cliente creado correctamente.',
                'data' => $customer
            ], 201);
        } catch (\Throwable $e) {

            DB::rollBack();
            Log::error('Error creando cliente: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error al crear el cliente.'
            ], 500);
        }
    }

    // ============================
    // UPDATE
    // ============================
    public function update(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'person_type' => 'required|in:natural,juridica',
            'document_type' => 'required|in:DNI,CE,RUC',
            'document_number' => [

                'required',

                'string',

                'max:20',

                function ($attribute, $value, $fail) use ($request, $customer) {

                    if (
                        $request->document_type === 'RUC' &&
                        Customer::where('ruc', $value)
                        ->where('id', '!=', $customer->id)
                        ->exists()
                    ) {

                        $fail('El RUC ya se encuentra registrado.');
                    }
                }
            ],

            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'business_name' => 'nullable|string|max:255',

            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',

            'channel' => 'nullable|string|max:100',
            'subchannel' => 'nullable|string|max:100',
            'withholding_agent' => 'nullable|boolean',

            'status' => 'nullable|boolean',
        ]);

        if ($data['document_type'] === 'RUC') {

            $data['first_name'] = null;
            $data['last_name'] = null;
            $data['full_name'] = $data['business_name'];

            $data['ruc'] = $data['document_number'];
        } else {

            $data['business_name'] = null;
            $data['ruc'] = null;
            $data['full_name'] = $data['first_name'] . ' ' . $data['last_name'];
        }

        $data['withholding_agent'] =
            $request->withholding_agent ?? 0;

        $data['status'] = (int) $request->status;
        try {

            DB::beginTransaction();

            if (Auth::check()) {

                $data['updated_by'] = Auth::id();
            }

            $customer->update($data);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Cliente actualizado correctamente.',
                'data' => $customer
            ]);
        } catch (\Throwable $e) {

            DB::rollBack();
            Log::error('Error actualizando cliente: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error al actualizar el cliente.'
            ], 500);
        }
    }

    // ============================
    // DELETE
    // ============================
    public function destroy($id)
    {
        try {
            $customer = Customer::findOrFail($id);

            $customer->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Cliente eliminado correctamente.'
            ]);
        } catch (\Throwable $e) {

            Log::error('Error eliminando cliente: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Error al eliminar el cliente.'
            ], 500);
        }
    }


    public function consultarDocumento($numero)
    {

        // Solo números
        if (!preg_match('/^\d+$/', $numero)) {
            return response()->json([
                'status'  => false,
                'message' => 'El número de documento debe contener solo dígitos.'
            ], 422);
        }

        // Solo aceptamos 8 (DNI) o 11 (RUC)
        if (strlen($numero) !== 8 && strlen($numero) !== 11) {
            return response()->json([
                'status'  => false,
                'message' => 'El número debe tener 8 dígitos (DNI) o 11 dígitos (RUC).'
            ], 422);
        }

        $token = 'apis-token-7645.70qIyk7rGHUBVYCLNlcITcM1fo-mBqvp';

        // ========= DNI (8 dígitos) =========
        if (strlen($numero) === 8) {

            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => 'https://api.apis.net.pe/v2/reniec/dni?numero=' . $numero,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => 0,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 2,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => [
                    'Referer: https://apis.net.pe/consulta-dni-api',
                    'Authorization: ' . 'Bearer ' . $token,
                ],
            ]);

            $response = curl_exec($curl);

            if ($response === false) {
                $error = curl_error($curl);
                curl_close($curl);
                return response()->json([
                    'status'  => false,
                    'message' => 'Error al conectar con el servicio de DNI.',
                    'error'   => $error,
                ], 500);
            }

            curl_close($curl);

            $persona = json_decode($response);

            if (!$persona || isset($persona->error)) {
                return response()->json([
                    'status'  => false,
                    'message' => 'DNI no encontrado.',
                    'data'    => $persona
                ], 404);
            }

            return response()->json([
                'status' => true,
                'type'   => 'DNI',
                'data'   => $persona,
            ]);
        }

        // ========= RUC (11 dígitos) =========
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://api.apis.net.pe/v2/sunat/ruc?numero=' . $numero,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => [
                'Referer: http://apis.net.pe/api-ruc',
                'Authorization: ' . 'Bearer ' . $token,
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
            'status' => true,
            'type'   => 'RUC',
            'data'   => $empresa,
        ]);
    }


    //FUNCION PARA BUSCAR UBIGEO DESDE EL SELECT2 EN EL FORMULARIO DE CREACION/EDICION DE CLIENTES

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

                'id'   => $ubigeo->id,

                'text' => $ubigeo->full_name

            ];
        }

        return response()->json($results);
    }
}
