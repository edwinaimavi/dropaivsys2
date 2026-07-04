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
        $this->middleware('can:admin.electronic-invoice-settings.index')->only(['index', 'list', 'show']);
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
        return DataTables::of(ElectronicInvoiceSetting::query()->with('company')->orderByDesc('id'))
            ->addColumn('company', fn (ElectronicInvoiceSetting $setting) =>
                $setting->company?->trade_name ?? $setting->company?->business_name ?? '-')
            ->addColumn('environment_label', fn (ElectronicInvoiceSetting $setting) =>
                $setting->environment === 'production' ? 'Produccion' : 'Beta')
            ->editColumn('is_active', fn (ElectronicInvoiceSetting $setting) =>
                $setting->is_active
                    ? '<span class="badge badge-success">Activo</span>'
                    : '<span class="badge badge-secondary">Inactivo</span>')
            ->addColumn('acciones', fn (ElectronicInvoiceSetting $setting) =>
                '<button class="btn btn-sm btn-outline-primary editElectronicInvoiceSetting" data-id="' . $setting->id . '"><i class="fas fa-edit"></i></button>')
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
