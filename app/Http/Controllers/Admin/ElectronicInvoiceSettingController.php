<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\ElectronicInvoiceSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
                "{$table}.*",
                'companies.id as company_table_id',
                'companies.business_name as company_business_name',
                'companies.trade_name as company_trade_name',
            ])
            ->orderByDesc("{$table}.id");

        return DataTables::eloquent($settings)
            ->addColumn('company', fn (ElectronicInvoiceSetting $setting) =>
                $setting->company_trade_name ?? $setting->company_business_name ?? '-')
            ->addColumn('environment_label', fn (ElectronicInvoiceSetting $setting) =>
                $setting->environment === 'production' ? 'Produccion' : 'Beta')
            ->editColumn('is_active', fn (ElectronicInvoiceSetting $setting) =>
                $setting->is_active
                    ? '<span class="badge badge-success rounded-pill px-3">Activo</span>'
                    : '<span class="badge badge-secondary rounded-pill px-3">Inactivo</span>')
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
            ->rawColumns(['is_active', 'acciones'])
            ->make(true);
    }

    public function show(ElectronicInvoiceSetting $electronicInvoiceSetting)
    {
        return response()->json([
            'status' => 'success',
            'data' => $electronicInvoiceSetting->load('company'),
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

    private function save(Request $request, ?ElectronicInvoiceSetting $setting = null)
    {
        $validated = $request->validate([
            'company_id' => ['nullable', 'exists:companies,id'],
            'provider' => ['required', 'string', 'max:50'],
            'environment' => ['required', Rule::in(['beta', 'production'])],
            'api_base_url' => ['nullable', 'string', 'max:255'],
            'api_token' => ['nullable', 'string'],
            'user_token' => ['nullable', 'string'],
            'ruc' => ['nullable', 'string', 'max:20'],
            'business_name' => ['nullable', 'string', 'max:255'],
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
            'company_id.exists' => 'La empresa seleccionada no existe.',
            'provider.required' => 'El proveedor es obligatorio.',
            'provider.max' => 'El proveedor no debe superar 50 caracteres.',
            'environment.required' => 'Debe seleccionar el ambiente.',
            'environment.in' => 'El ambiente seleccionado no es válido.',
            'api_base_url.max' => 'La URL API no debe superar 255 caracteres.',
            'ruc.max' => 'El RUC no debe superar 20 caracteres.',
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
                'provider' => $validated['provider'] ?? 'apisperu',
                'is_active' => (bool) ($validated['is_active'] ?? true),
                'updated_by' => Auth::id(),
            ]);

            if ($setting) {
                $setting->update($data);
            } else {
                $data['created_by'] = Auth::id();
                $setting = ElectronicInvoiceSetting::create($data);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Configuracion de facturacion guardada correctamente.',
                'data' => $setting,
            ], $setting->wasRecentlyCreated ? 201 : 200);
        } catch (\Throwable $e) {
            Log::error('Error saving electronic invoice setting: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo guardar la configuracion.',
            ], 500);
        }
    }
}
