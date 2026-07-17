<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\ElectronicInvoiceSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class ElectronicInvoiceSettingController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:admin.electronic-invoice-settings.index')->only(['index', 'list']);
        $this->middleware('can:admin.electronic-invoice-settings.show')->only(['show']);
        $this->middleware('can:admin.electronic-invoice-settings.store')->only(['store']);
        $this->middleware('can:admin.electronic-invoice-settings.update')->only(['update']);
        $this->middleware('can:admin.electronic-invoice-settings.index')->only(['consultRuc']);
    }

    public function index()
    {
        $companies = Company::query()->where('status', true)->orderBy('business_name')->get();

        return view('admin.electronic-invoice-settings.index', compact('companies'));
    }

    public function list()
    {
        $table = (new ElectronicInvoiceSetting())->getTable();

        $settings = ElectronicInvoiceSetting::query()
            ->leftJoin('companies', 'companies.id', '=', "{$table}.company_id")
            ->select([
                "{$table}.id",
                "{$table}.company_id",
                "{$table}.provider",
                "{$table}.environment",
                "{$table}.ruc",
                "{$table}.business_name",
                "{$table}.is_active",
                "{$table}.created_at",
                'companies.id as company_table_id',
                'companies.business_name as company_business_name',
                'companies.trade_name as company_trade_name',
            ])
            ->selectRaw("CASE WHEN {$table}.api_token IS NOT NULL AND {$table}.api_token <> '' THEN 1 ELSE 0 END AS has_api_token")
            ->selectRaw("CASE WHEN {$table}.sol_user IS NOT NULL AND {$table}.sol_user <> '' AND {$table}.sol_password IS NOT NULL AND {$table}.sol_password <> '' THEN 1 ELSE 0 END AS has_sol_credentials")
            ->orderByDesc("{$table}.id");

        return DataTables::eloquent($settings)
            ->addColumn('company', fn (ElectronicInvoiceSetting $setting) =>
                $setting->company_trade_name ?? $setting->company_business_name ?? '-')
            ->addColumn('environment_label', fn (ElectronicInvoiceSetting $setting) => match ($setting->environment) {
                'production' => 'Producción',
                'internal' => 'Interno',
                default => 'Beta / Pruebas',
            })
            ->addColumn('provider_label', fn (ElectronicInvoiceSetting $setting) =>
                $setting->provider === 'internal' ? 'Modo interno' : 'APIs Perú / SUNAT')
            ->addColumn('credentials_status', function (ElectronicInvoiceSetting $setting) {
                $token = $setting->has_api_token ? 'Token configurado' : 'Token pendiente';
                $sol = $setting->has_sol_credentials ? 'SOL configurado' : 'SOL pendiente';
                return '<span class="badge badge-light border mr-1">' . $token . '</span><span class="badge badge-light border">' . $sol . '</span>';
            })
            ->editColumn('is_active', fn (ElectronicInvoiceSetting $setting) =>
                $setting->is_active
                    ? '<span class="badge badge-success rounded-pill px-3">Activo</span>'
                    : '<span class="badge badge-secondary rounded-pill px-3">Inactivo</span>')
            ->editColumn('created_at', fn (ElectronicInvoiceSetting $setting) =>
                optional($setting->created_at)->format('d/m/Y H:i'))
            ->addColumn('acciones', function (ElectronicInvoiceSetting $setting) {
                $buttons = '<div class="btn-group" role="group">';

                if (auth()->user()?->can('admin.electronic-invoice-settings.show')) {
                    $buttons .= '<button class="btn btn-sm btn-outline-info viewElectronicInvoiceSetting" data-id="' . $setting->id . '" title="Ver"><i class="fas fa-eye"></i></button>';
                }

                if (auth()->user()?->can('admin.electronic-invoice-settings.update')) {
                    $buttons .= '<button class="btn btn-sm btn-outline-primary editElectronicInvoiceSetting" data-id="' . $setting->id . '" title="Editar"><i class="fas fa-edit"></i></button>';
                }

                return $buttons . '</div>';
            })
            ->orderColumn('id', "{$table}.id $1")
            ->orderColumn('company', 'companies.business_name $1')
            ->orderColumn('provider', "{$table}.provider $1")
            ->orderColumn('environment_label', "{$table}.environment $1")
            ->orderColumn('ruc', "{$table}.ruc $1")
            ->orderColumn('business_name', "{$table}.business_name $1")
            ->orderColumn('is_active', "{$table}.is_active $1")
            ->rawColumns(['credentials_status', 'is_active', 'acciones'])
            ->make(true);
    }

    public function show(ElectronicInvoiceSetting $electronicInvoiceSetting)
    {
        $data = $electronicInvoiceSetting->only([
            'id', 'company_id', 'provider', 'environment', 'api_base_url', 'ruc',
            'business_name', 'trade_name', 'address', 'ubigeo', 'department',
            'province', 'district', 'certificate_path', 'logo_path', 'is_active',
        ]);
        $data['has_api_token'] = filled($electronicInvoiceSetting->api_token);
        $data['has_user_token'] = filled($electronicInvoiceSetting->user_token);
        $data['has_sol_password'] = filled($electronicInvoiceSetting->sol_password);
        $data['has_sol_user'] = filled($electronicInvoiceSetting->sol_user);
        $data['company'] = $electronicInvoiceSetting->company;

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    public function store(Request $request)
    {
        return $this->save($request);
    }

    public function update(Request $request, ElectronicInvoiceSetting $electronicInvoiceSetting)
    {
        return $this->save($request, $electronicInvoiceSetting);
    }

    public function consultRuc(string $numero)
    {
        $response = app(CompanyController::class)->consultarRuc($numero);
        $payload = $response->getData(true);

        if ($response->getStatusCode() >= 400) {
            return response()->json([
                'success' => false,
                'message' => $payload['message'] ?? 'No se encontraron datos para el RUC ingresado.',
            ], $response->getStatusCode());
        }

        return response()->json([
            'success' => true,
            'razon_social' => $payload['razon_social'] ?? '',
            'nombre_comercial' => $payload['data']['nombreComercial'] ?? '',
            'direccion' => $payload['direccion'] ?? '',
            'ubigeo' => $payload['data']['ubigeo'] ?? ($payload['data']['ubigeoSunat'] ?? ''),
            'departamento' => $payload['departamento'] ?? '',
            'provincia' => $payload['provincia'] ?? '',
            'distrito' => $payload['distrito'] ?? '',
        ]);
    }

    private function save(Request $request, ?ElectronicInvoiceSetting $setting = null)
    {
        $validated = $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
            'provider' => ['required', Rule::in(['apisperu', 'internal'])],
            'environment' => ['required', Rule::in(['internal', 'beta', 'production'])],
            'api_base_url' => ['nullable', 'url', 'max:255'],
            'api_token' => ['nullable', 'string'],
            'user_token' => ['nullable', 'string'],
            'ruc' => ['required', 'digits:11'],
            'business_name' => ['required', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'ubigeo' => ['nullable', 'string', 'max:10'],
            'department' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'sol_user' => ['nullable', 'string', 'max:255'],
            'sol_password' => ['nullable', 'string'],
            'certificate_path' => ['nullable', 'string', 'max:255'],
            'logo_path' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'company_id.required' => 'La empresa es obligatoria.',
            'company_id.exists' => 'La empresa seleccionada no existe.',
            'provider.required' => 'El proveedor es obligatorio.',
            'provider.in' => 'El proveedor seleccionado no es válido.',
            'environment.required' => 'Debe seleccionar el ambiente.',
            'environment.in' => 'El ambiente seleccionado no es válido.',
            'api_base_url.max' => 'La URL API no debe superar 255 caracteres.',
            'api_base_url.url' => 'La URL API debe tener un formato válido.',
            'ruc.required' => 'El RUC es obligatorio.',
            'ruc.digits' => 'El RUC debe tener 11 dígitos.',
            'business_name.required' => 'La razón social es obligatoria.',
            'business_name.max' => 'La razón social no debe superar 255 caracteres.',
            'trade_name.max' => 'El nombre comercial no debe superar 255 caracteres.',
            'ubigeo.max' => 'El ubigeo no debe superar 10 caracteres.',
            'department.max' => 'El departamento no debe superar 255 caracteres.',
            'province.max' => 'La provincia no debe superar 255 caracteres.',
            'district.max' => 'El distrito no debe superar 255 caracteres.',
            'sol_user.max' => 'El usuario SOL no debe superar 255 caracteres.',
            'is_active.boolean' => 'El estado activo no es válido.',
        ]);

        try {
            $data = array_merge($validated, [
                'provider' => mb_strtolower($validated['provider']),
                'is_active' => (bool) ($validated['is_active'] ?? true),
                'updated_by' => Auth::id(),
            ]);

            foreach (['api_token', 'user_token', 'sol_user', 'sol_password'] as $credential) {
                if ($setting && blank($validated[$credential] ?? null)) {
                    unset($data[$credential]);
                }
            }

            $setting = DB::transaction(function () use ($setting, $data) {
                if ($data['is_active']) {
                    $duplicate = ElectronicInvoiceSetting::query()
                        ->where('company_id', $data['company_id'])
                        ->where('provider', $data['provider'])
                        ->where('environment', $data['environment'])
                        ->where('is_active', true)
                        ->when($setting, fn ($query) => $query->whereKeyNot($setting->id))
                        ->lockForUpdate()
                        ->exists();

                    if ($duplicate) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'is_active' => 'Ya existe una configuración activa para esta empresa, proveedor y ambiente.',
                        ]);
                    }
                }

                if ($setting) {
                    $setting->update($data);
                    return $setting;
                }

                return ElectronicInvoiceSetting::create($data + ['created_by' => Auth::id()]);
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Configuracion de facturacion guardada correctamente.',
                'data' => ['id' => $setting->id],
            ], $setting->wasRecentlyCreated ? 201 : 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Error saving electronic invoice setting: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo guardar la configuracion.',
            ], 500);
        }
    }
}
