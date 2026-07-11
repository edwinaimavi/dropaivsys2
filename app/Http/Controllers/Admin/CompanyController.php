<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class CompanyController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:admin.companies.index')->only(['index', 'list', 'consultarRuc']);
        $this->middleware('can:admin.companies.store')->only(['store']);
        $this->middleware('can:admin.companies.show')->only(['show']);
        $this->middleware('can:admin.companies.update')->only(['edit', 'update']);
        $this->middleware('can:admin.companies.destroy')->only(['destroy']);
    }

    public function index()
    {
        return view('admin.companies.index');
    }

    public function list()
    {
        $companies = Company::query()->orderByDesc('id');

        return DataTables::of($companies)
            ->addIndexColumn()
            ->addColumn('logo_preview', function (Company $company) {
                if (!$company->logo) {
                    return '<span class="company-avatar"><i class="fas fa-building"></i></span>';
                }

                return '<img src="' . e(Storage::url($company->logo)) . '" class="company-logo-thumb" alt="Logo">';
            })
            ->addColumn('location', fn (Company $company) => $company->address ?: '-')
            ->editColumn('status', fn (Company $company) => $this->statusBadge($company->status))
            ->editColumn('created_at', fn (Company $company) => $company->created_at?->format('d/m/Y H:i') ?? '-')
            ->addColumn('acciones', fn (Company $company) => view('admin.companies.partials.acciones', compact('company'))->render())
            ->rawColumns(['logo_preview', 'status', 'acciones'])
            ->make(true);
    }

    public function store(Request $request)
    {
        $validated = $this->validatedData($request);

        try {
            $company = DB::transaction(function () use ($validated, $request) {
                $data = $this->prepareData($validated);

                if ($request->hasFile('logo')) {
                    $data['logo'] = $request->file('logo')->store('companies', 'public');
                }

                return Company::create($data);
            });

            return response()->json([
                'status' => 'success',
                'success' => true,
                'message' => 'Empresa registrada correctamente.',
                'data' => $this->payload($company),
            ], 201);
        } catch (\Throwable $e) {
            Log::error('Error creating company: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'success' => false,
                'message' => 'No se pudo registrar la empresa.',
            ], 500);
        }
    }

    public function show(Company $company)
    {
        return response()->json([
            'status' => 'success',
            'success' => true,
            'data' => $this->payload($company),
        ]);
    }

    public function edit(Company $company)
    {
        return $this->show($company);
    }

    public function update(Request $request, Company $company)
    {
        $validated = $this->validatedData($request, $company);
        $oldLogo = $company->logo;

        try {
            DB::transaction(function () use ($company, $validated, $request) {
                $data = $this->prepareData($validated);

                if ($request->hasFile('logo')) {
                    $data['logo'] = $request->file('logo')->store('companies', 'public');
                }

                $company->update($data);
            });

            if ($request->hasFile('logo') && $oldLogo && $oldLogo !== $company->fresh()->logo) {
                Storage::disk('public')->delete($oldLogo);
            }

            return response()->json([
                'status' => 'success',
                'success' => true,
                'message' => 'Empresa actualizada correctamente.',
                'data' => $this->payload($company->fresh()),
            ]);
        } catch (\Throwable $e) {
            Log::error('Error updating company: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'success' => false,
                'message' => 'No se pudo actualizar la empresa.',
            ], 500);
        }
    }

    public function destroy(Company $company)
    {
        $usage = $this->usageSummary($company);

        if ($usage !== []) {
            return response()->json([
                'status' => 'error',
                'success' => false,
                'message' => 'No se puede eliminar esta empresa porque está siendo utilizada en ' . implode(', ', $usage) . '.',
            ], 409);
        }

        try {
            DB::transaction(fn () => $company->delete());

            return response()->json([
                'status' => 'success',
                'success' => true,
                'message' => 'Empresa eliminada correctamente.',
            ]);
        } catch (QueryException $e) {
            Log::warning('Company delete blocked by integrity: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'success' => false,
                'message' => 'No se puede eliminar esta empresa porque está siendo utilizada en otros registros.',
            ], 409);
        } catch (\Throwable $e) {
            Log::error('Error deleting company: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'success' => false,
                'message' => 'No se pudo eliminar la empresa.',
            ], 500);
        }
    }

    public function consultarRuc(string $numero)
    {
        if (!preg_match('/^\d+$/', $numero)) {
            return response()->json([
                'status' => false,
                'success' => false,
                'message' => 'El RUC debe contener solo dígitos.',
            ], 422);
        }

        if (strlen($numero) !== 11) {
            return response()->json([
                'status' => false,
                'success' => false,
                'message' => 'El RUC debe tener 11 dígitos.',
            ], 422);
        }

        if (!str_starts_with($numero, '10') && !str_starts_with($numero, '20')) {
            return response()->json([
                'status' => false,
                'success' => false,
                'message' => 'El RUC debe iniciar con 10 o 20.',
            ], 422);
        }

        $token = env('APIS_PERU_TOKEN', 'apis-token-7645.70qIyk7rGHUBVYCLNlcITcM1fo-mBqvp');
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://api.apis.net.pe/v2/sunat/ruc?numero=' . $numero,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 20,
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
                'status' => false,
                'success' => false,
                'message' => 'Error al conectar con el servicio de RUC.',
                'error' => $error,
            ], 500);
        }

        curl_close($curl);
        $company = json_decode($response);

        if (!$company || isset($company->error) || (empty($company->nombre) && empty($company->razonSocial))) {
            return response()->json([
                'status' => false,
                'success' => false,
                'message' => 'El RUC ingresado no existe.',
                'data' => $company,
            ], 404);
        }

        $businessName = $company->nombre ?? $company->razonSocial ?? '';
        $address = trim(($company->direccion ?? $company->domicilioFiscal ?? '') . (
            !empty($company->distrito) || !empty($company->provincia) || !empty($company->departamento)
                ? ' - ' . collect([$company->distrito ?? null, $company->provincia ?? null, $company->departamento ?? null])->filter()->implode(' - ')
                : ''
        ));

        return response()->json([
            'status' => true,
            'success' => true,
            'type' => 'RUC',
            'data' => $company,
            'razon_social' => mb_strtoupper($businessName, 'UTF-8'),
            'direccion' => mb_strtoupper($address, 'UTF-8'),
            'departamento' => mb_strtoupper($company->departamento ?? '', 'UTF-8'),
            'provincia' => mb_strtoupper($company->provincia ?? '', 'UTF-8'),
            'distrito' => mb_strtoupper($company->distrito ?? '', 'UTF-8'),
            'estado' => mb_strtoupper($company->estado ?? '', 'UTF-8'),
            'condicion' => mb_strtoupper($company->condicion ?? '', 'UTF-8'),
        ]);
    }

    private function validatedData(Request $request, ?Company $company = null): array
    {
        return $request->validate([
            'ruc' => [
                'required',
                'digits:11',
                Rule::unique('companies', 'ruc')
                    ->ignore($company?->id)
                    ->whereNull('deleted_at'),
            ],
            'business_name' => ['required', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'status' => ['required', 'boolean'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'ruc.required' => 'El RUC es obligatorio.',
            'ruc.digits' => 'El RUC debe tener 11 dígitos.',
            'ruc.unique' => 'Este RUC ya está registrado.',
            'business_name.required' => 'La razón social es obligatoria.',
            'email.email' => 'El correo electrónico no tiene un formato válido.',
            'status.required' => 'El estado es obligatorio.',
            'status.boolean' => 'El estado seleccionado no es válido.',
            'logo.image' => 'El logo debe ser una imagen.',
            'logo.mimes' => 'El logo debe ser JPG, PNG o WEBP.',
            'logo.max' => 'El logo no debe superar 2 MB.',
        ]);
    }

    private function prepareData(array $validated): array
    {
        return [
            'ruc' => $validated['ruc'],
            'business_name' => $this->upperOrNull($validated['business_name']),
            'trade_name' => $this->upperOrNull($validated['trade_name'] ?? null),
            'address' => $this->upperOrNull($validated['address'] ?? null),
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'status' => (bool) $validated['status'],
        ];
    }

    private function payload(Company $company): array
    {
        return [
            'id' => $company->id,
            'business_name' => $company->business_name,
            'trade_name' => $company->trade_name,
            'ruc' => $company->ruc,
            'email' => $company->email,
            'phone' => $company->phone,
            'address' => $company->address,
            'logo' => $company->logo,
            'logo_url' => $company->logo ? Storage::url($company->logo) : null,
            'status' => (bool) $company->status,
            'status_label' => $company->status ? 'ACTIVO' : 'INACTIVO',
            'created_at' => $company->created_at?->format('d/m/Y H:i'),
            'updated_at' => $company->updated_at?->format('d/m/Y H:i'),
            'usage' => $this->usageSummary($company),
        ];
    }

    private function usageSummary(Company $company): array
    {
        $usage = [];
        $checks = [
            'cotizaciones' => 'quotes',
            'órdenes de compra de clientes' => 'customer_purchase_orders',
            'órdenes de compra a proveedores' => 'supplier_purchase_orders',
            'ingresos de almacén' => 'warehouse_entries',
            'rotulaciones' => 'customer_order_labelings',
            'comprobantes electrónicos' => 'electronic_invoices',
            'configuración electrónica' => 'electronic_invoice_settings',
            'series electrónicas' => 'electronic_invoice_series',
        ];

        foreach ($checks as $label => $table) {
            if (DB::getSchemaBuilder()->hasTable($table)
                && DB::table($table)->where('company_id', $company->id)->exists()) {
                $usage[] = $label;
            }
        }

        return $usage;
    }

    private function statusBadge(bool $status): string
    {
        $class = $status ? 'badge-info' : 'badge-danger';
        $label = $status ? 'ACTIVO' : 'INACTIVO';

        return '<span class="badge ' . $class . ' text-light rounded-pill px-3 py-2 shadow-sm">' . $label . '</span>';
    }

    private function upperOrNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? mb_strtoupper($value, 'UTF-8') : null;
    }
}
